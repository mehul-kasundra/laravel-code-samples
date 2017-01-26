<?php

namespace Tallyfy\API\V1\Controllers\Checklists;

use Dingo\Api\Exception\ResourceException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use Illuminate\Support\Collection;
use Input;
use Illuminate\Http\Response;
use Tallyfy\API\V1\Controllers\TenantsController;
use Tallyfy\API\V1\Models\Checklist;
use Tallyfy\API\V1\Models\Task;
use Tallyfy\API\V1\Repositories\ChecklistsRepository;
use Tallyfy\API\V1\Transformers\ChecklistTransformer;
use Tallyfy\API\Exceptions\AccessDeniedException;
use Tallyfy\API\V1\Transformers\CommentTransformer;
use Tallyfy\API\V1\Transformers\StepTransformer;
use Tallyfy\API\V1\Transformers\FileTransformer;

class ChecklistsController extends TenantsController
{

    function __construct(ChecklistTransformer $transformer, ChecklistsRepository $repo)
    {
        $this->repository = $repo;
        $this->transformer = $transformer;
        $this->permission_prefix = 'checklists';
        parent::__construct();
        ini_set('max_execution_time', 60);
    }

    /**
     * @SWG\GET(
     *     path="/organizations/{org}/checklists/public/{id}",
     *     description="Get checklist by Id belong to organization",
     *     operationId="getOrganizationPublicChecklistById",
     *     tags={"Checklist"},
     *     produces={
     *         "application/json"
     *     },
     *     @SWG\Parameter(
     *         name="org",
     *         type="string",
     *         in="path",
     *         required=true
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         type="string",
     *         in="path",
     *         required=true
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Success response",
     *         @SWG\Schema(ref="#/definitions/Checklist")
     *     )
     * )
     */
    public function publicChecklist()
    {
        set_tenant_hard(func_get_arg(0));
        $checklist = $this->repository->getByMeta('settings', 'is_public', array('yes', 'with-link'), func_get_arg(1));
        return $this->withItem($checklist, $this->transformer);
    }

    /**
     * @SWG\GET(
     *     path="/organizations/{org}/checklists/public",
     *     description="Get all public checklists belonging to organization",
     *     operationId="getOrganizationPublicChecklists",
     *     tags={"Checklist"},
     *     produces={
     *         "application/json"
     *     },
     *     @SWG\Parameter(
     *         name="org",
     *         type="string",
     *         in="path",
     *         required=true
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Success response"
     *     )
     * )
     */
    public function publicChecklists()
    {
        set_tenant_hard(func_get_arg(0));
        $checklist = $this->repository->getByMeta('settings', 'is_public', array('yes'));
        return $this->withCollection($checklist, $this->transformer);
    }

    /**
     * @SWG\Post(
     *     path="/organizations/{org}/checklists/import",
     *     description="Import checklists",
     *     operationId="checklistImport",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     consumes={"multipart/form-data"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="file[]",
     *         in="formData",
     *         description="exported checklist file",
     *         required=true,
     *         type="file"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         @SWG\Schema(ref="#/definitions/GenericObject")
     *     )
     * )
     */
    public function import()
    {
        set_tenant(func_get_arg(0));
        $request = \App::make('Tallyfy\API\Http\Requests\Checklists\ChecklistImportRequest');
        $checklist = $this->repository->import($request->all());
        return $this->withItem($checklist, $this->transformer);
    }

    /**
     * @SWG\Get(
     *     path="/organizations/{org}/checklists/{id}/export",
     *     description="Export checklists",
     *     operationId="checklistExport",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checklist Id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              @SWG\Property(
     *                  property="data",
     *                  ref="#/definitions/File"
     *              )
     *         )
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Checklist not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         )
     *     )
     * )
     */
    function export($tenant, $id)
    {
        set_tenant($tenant);
        $checklist = $this->repository->export($id);
        return $this->withItem($checklist, new FileTransformer());
    }

    /**
     *
     * @SWG\Post(
     *     path="/organizations/{org}/checklists/{checklist_id}/import-steps",
     *     description="Build multiple steps quickly",
     *     operationId="checklistImportSteps",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *          description="Checklist",
     *          name="checklist_id",
     *          in="path",
     *          required=true,
     *          type="string"
     *      ),
     *     @SWG\Parameter(
     *         name="steps",
     *         in="body",
     *         description="Steps to be imported",
     *         required=true,
     *         @SWG\Schema(
     *              ref="#/definitions/checklistInput"
     *         )
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              type="array",
     *              @SWG\Items(ref="#/definitions/Step")
     *         )
     *     )
     * )
     */
    public function importSteps()
    {
        set_tenant(func_get_arg(0));
        hasAccess('steps.create');
        $checklist = $this->repository->getByKey(func_get_arg(1));
        $stepRepo = \App::make('Tallyfy\API\V1\Repositories\StepsRepository');
        $ctrl = \App::make('Tallyfy\API\V1\Controllers\Checklists\StepsController');
        $steps = \Input::get('steps');
        if (empty($steps)) {
            throw new ResourceException('Steps should not be empty.');
        }
        $response = [];

        foreach ($steps as $key => $step) {
            $step['checklist_id'] = $checklist->id;
            \Request::replace($step);
            $ctrl->prepareInput();
            $input = $ctrl->getInput();
            $step_id = isset($step['id']) && !empty($step['id']) ? $step['id'] : null;
            if (!$step_id) {
                $createdStep = $stepRepo->create($input);
                $step['id'] = $createdStep->id;
                $step['position'] = $createdStep->position;
                \Request::replace($step);
                $ctrl->prepareInput();
                $input = $ctrl->getInput();
                $response[] = $createdStep;
            }
            $stepRepo->update($createdStep->id, $input);
        }

        return $this->withCollection($response, new StepTransformer());
    }

    function update($tenant)
    {
        set_tenant($tenant);
        $checklistId = \Request::segment($this->key_segment) ?: $this->id;
        $this->prepareInput($this->repository->model->default);
        if ($this->validate($checklistId)) {
            $checklist = $this->repository->getByKey($checklistId);
            if ((\API::user()->id == $checklist->user_id) || \API::user()->hasAccess($this->permission_prefix . '.update')) {
                return $this->repository->update($checklistId, $this->input);
            } else {
                throw new AccessDeniedException('You do not have permissions to perform this task', 403);
            }
        }
    }

    function updateMultiple($tenant)
    {
        set_tenant($tenant);
        $originalInput = Input::all();
        $result = [
            'data' => [],
            'errors' => null
        ];
        $failedChecklistsUpdateIDs = [];
        $failedChecklistsUpdateErrors = [];

        if (isset($originalInput['checklists'])) {
            foreach ($originalInput['checklists'] as $checklist) {
                $checklistID = $checklist['id'];
                $this->setID($checklistID);
                Input::replace($input = $checklist);

                try {
                    $result['data'][] = $this->update($tenant);
                } catch (AccessDeniedException $ex) {
                    $result['errors'] = 'You don\'t have permissions to update checklists';
                    $result['data'] = json_decode($this->withCollection($result['data'],
                        $this->transformer)->getContent())->data;

                    return $result;
                } catch (\Exception $ex) {
                    $failedChecklistsUpdateIDs[] = $checklistID;
                    $failedChecklistsUpdateErrors[] = $ex->getMessage();
                }
            }
        }

        if (count($failedChecklistsUpdateIDs)) {
            $result['errors'] = 'There were problems updating some of the checklists: <br /><br />';
            $result['errors'] .= implode('<br />', $failedChecklistsUpdateErrors);
        }

        $result['data'] = json_decode($this->withCollection($result['data'], $this->transformer)->getContent())->data;

        return $result;
    }

    public function prepareInput(array $previous = array())
    {
        Input::merge(['checklist_public_name' => Input::get('checklist_alias')]);
        parent::prepareInput($previous);
    }

    /**
     * @SWG\Put(
     *     path="/organizations/{org}/checklists/{id}/activate",
     *     description="Activate checklists",
     *     operationId="checklistActivate",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checklist Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              @SWG\Property(
     *                  property="data",
     *                  ref="#/definitions/Checklist"
     *              )
     *         )
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Checklist not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         )
     *     ),
     *    @SWG\Response(
     *         response="403",
     *         description="Do not have permission",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         )
     *     )
     * )
     */
    public function activate($tenant)
    {
        set_tenant($tenant);
        hasAccess('checklists.archive');
        $id = \Request::segment($this->key_segment);
        $checklist = $this->repository->getByKey($id, false, true);

        if( api_locked($checklist, 'PUT') ) {
            throw new UpdateResourceFailedException('Checklist is archiving, restoring disabled');
        }

        if ($checklist && $checklist->deleted_at) {
            return $this->repository->restore($id);
        } else {
            throw new AccessDeniedException('You do not have permissions to perform this task', 403);
        }
    }

    /**
     *
     * @SWG\Get(
     *     path="/organizations/{org}/checklists/{id}/comments",
     *     description="Get checklist's comments",
     *     operationId="getChecklistComments",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checklist Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              @SWG\Property(
     *                  property="data",
     *                  type="array",
     *                  @SWG\Items(ref="#/definitions/Comment")
     *              )
     *         )
     *     ),
     * )
     * */
    public function comments($tenant, $id)
    {
        set_tenant($tenant);
        $checklist = $this->repository->getByKey($id, true, true);
        return $this->withCollection($checklist->comments->sortByDesc('created_at'), new CommentTransformer());
    }

    /**
     * @SWG\Put(
     *     path="/organizations/{org}/checklists/{id}/restore",
     *     description="Restore archived checklist",
     *     operationId="restoreArchivedChecklist",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checklist Id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              @SWG\Property(
     *                  property="data",
     *                  ref="#/definitions/Checklist"
     *              )
     *         )
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Checklist not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         )
     *     ),
     *    @SWG\Response(
     *         response="403",
     *         description="Do not have permission",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         )
     *     )
     * )
     *
     */
    function checklistStatistic($org, $id)
    {
        set_tenant($org);
        $checklist = Checklist::find($id);
        $steps = $checklist->steps()->lists('title', 'position');
        $taskTable = with(new Task())->getTable();
        $completedTasks = $checklist->tasks()->where("$taskTable.status", 'completed')->get();
        $data = [];
        $dueTasks = $checklist->tasks()->whereNotNull("due_at")->get();
        foreach ($completedTasks as $task) {
            if (!isset($data['completedTasks'][$task->step->position])) {
                $data['completedTasks'][$task->step->position] = 1;
            } else {
                $data['completedTasks'][$task->step->position] += 1;
            }
        }
        foreach ($dueTasks as $task) {
            if (!isset($data['dueTasks'][$task->step->position])) {
                $data['dueTasks'][$task->step->position] = 1;
            } else {
                $data['dueTasks'][$task->step->position] += 1;
            }
        }
        return \Response::json(['data' => $data, 'steps' => $steps]);
    }

    /**
     *
     * @SWG\Post(
     *     path="/organizations/{org}/organizations/{tenant}/checklists/{checklist_id}/clone",
     *     description="Clone checklist from other organization",
     *     operationId="cloneChecklist",
     *     produces={"application/json"},
     *     tags={"Checklist"},
     *     @SWG\Parameter(
     *         name="org",
     *         in="path",
     *         description="Organization Id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="tenant",
     *         in="path",
     *         description="Checklists Organization Id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *          description="Checklist",
     *          name="checklist_id",
     *          in="path",
     *          required=true,
     *          type="string"
     *      ),
     *     @SWG\Parameter(
     *          description="Master process title",
     *          name="title",
     *          in="body",
     *          required=false,
     *          type="string"
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         schema = @SWG\Definition (
     *              type="array",
     *              @SWG\Items(ref="#/definitions/Checklist")
     *         )
     *     )
     * )
     */
    public function cloneChecklist($org, $tenant, $id)
    {
        set_tenant($org);
        if ($tenant == $org) {
            throw new ResourceException('You can not clone checklist from your organization.');
        }

        set_tenant_hard($tenant);
        $original = $this->repository->getByMeta('settings', 'is_public', ['yes', 'with-link'], $id);

        set_tenant($org);
        $new_title = \Input::get('title');
        $checklist = $this->repository->cloneChecklist($original, $new_title);

        return $this->withItem($checklist, $this->transformer);
    }

    public function delete($org)
    {
        $data = [];
        if (\API::user()->is_support) {
            $data = parent::delete($org);
        }
        return \Response::json(['data' => $data]);
    }
}
