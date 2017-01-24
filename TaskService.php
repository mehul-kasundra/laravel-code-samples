<?php 
namespace App\Services;
use App\Models\Task\Task;
use App\Models\Task\TaskDefault;
use App\Helpers\ActionResult;
use App\Services\StageService;
use App\Services\TeamService;
use App\Services\FinanceService;
use App\Services\MailService;
use App\Services\Notification\Notify;
use App\Boxes\TeamBox;
use App\User;
use Carbon\Carbon;
use App\Models\Media\Media;
use Illuminate\Support\Collection;
use App\Jobs\SendTaskCompletedEmail;
use App\Jobs\SendTaskRolledBackEmail;
use App\Jobs\SendTaskDelayedEmail;
use App\Jobs\SendTaskAcceptedEmail;
use App\Jobs\SendTaskRejectedEmail;
use App\Jobs\SendTaskAssignedEmail;
use App\Jobs\SendTaskFollowersEmail;
use App\Jobs\UpdateUserScore;
use Validator, DB, Auth, App, Mail, Cache, File;
class TaskService
{
    protected $teamService, $teamBox, $mailService;
    public function __construct(TeamService $teamService, TeamBox $teamBox, MailService $mailService)
    {
        $this->teamService = $teamService;
        $this->teamBox = $teamBox;
        $this->mailService = $mailService;
    }
    public function getDashboardTasksData($userId, $foreignTeamId)
    {
        $tasks = $this->getUserAvailableTasks($userId, $foreignTeamId);
        return $tasks;
    }
    /**
     * Get tasks available for user (own account / foreign team)
     */
    public function getUserAvailableTasks($userId, $foreignTeamId)
    {
        $query = $this->teamBox->buildUserAvailableTasksQuery($userId, $foreignTeamId);
        $tasks = $query->get();
        return $tasks;
    }
    public function getUserAvailableTask($userId, $id)
    {
        $query = $this->teamBox->buildUserAvailableTasksQuery($userId, null);
        $task = $query->where('tasks.id', $id)->first();
        return $task;
    }
    public function getTaskById($id)
    {
        $task = Task::query()
                    ->with('user', 'assigneeTeamMember', 'taskFollowers', 'taskFollowers.teamMember', 'taskFollowers.teamMember.member')
                    ->where('id', $id)
                    ->first();
        return $task;
    }
    public function getTaskByHash($hash)
    {
        $task = Task::query()
                    ->with('user', 'assigneeTeamMember', 'taskFollowers', 'taskFollowers.teamMember', 'taskFollowers.teamMember.member')
                    ->where('hash', $hash)
                    ->first();
        return $task;
    }
    public function getUserTask($userId, $id)
    {
        $task = Task::query()
                    ->with('user', 'assigneeTeamMember', 'taskFollowers', 'taskFollowers.teamMember', 'taskFollowers.teamMember.member')
                    ->where('user_id', $userId)
                    ->where('id', $id)
                    ->first();
        return $task;
    }
    public function createDefaultTasks($userId)
    {
        $teamMemberId = $this->teamService->getTeamMemberIdByOwnerId($userId);
        $stageService = App::make(StageService::class);
        $defaultStagesToUserStages = $stageService
            ->getUserStages($userId)
            ->pluck('id', 'default_stage_id')
            ->toArray();
        $defaults = TaskDefault::query()
                               ->whereIn('default_stage_id', array_keys($defaultStagesToUserStages))
                               ->orderBy('sort', 'asc')
                               ->get();
        Task::query()
            ->where('user_id', $userId)
            ->whereNotNull('default_task_id')
            ->delete();
        foreach ($defaults as $taskDefault) {
            $taskDefault = $taskDefault->toArray();
            $task = $this->createNewTask($userId);
            $stageId = $defaultStagesToUserStages[$taskDefault['default_stage_id']];
            unset($taskDefault['default_stage_id'], $taskDefault['created_at'], $taskDefault['updated_at']);
            $task->fill($taskDefault);
            $task->default_task_id = $taskDefault['id'];
            $task->stage_id = $stageId;
            $task->assignee_team_member_id = $teamMemberId;
            $task->assign_status = TASK::ASSIGN_STATUS_ACCEPTED;
            $task->priority = Task::DEFAULT_PRIORITY;
            $task->due_date = null;
            $task->save();
        }
    }
    public function completeTaskById($userId, $taskId)
    {
        $result = new ActionResult();
        if ($userId && $taskId) {
            $query = $this->teamBox->buildUserAvailableTasksActionsQuery($userId, $taskId);
            $task = $query
                ->where('completed', 0)
                ->first();
            if ($task) {
                $task->update(['completed' => 1]);
                // notification
                Notify::completeTask($userId, $taskId);
                $result->allOK();
                $isStageCompleted = $this->isAllTasksCompletedInStage($userId, $task->stage_id);
                $this->setStageCompletedData($result, $task->stage_id, $isStageCompleted);
                dispatch((new UpdateUserScore($userId))->delay(2)->onQueue('jobs'));
                dispatch((new SendTaskCompletedEmail($taskId))->delay(3)->onQueue('emails'));
            } else {
                $result->error404();
            }
        }
        return $result;
    }
    public function rollbackTaskById($userId, $taskId)
    {
        $result = new ActionResult();
        if ($userId && $taskId) {
            $query = $this->teamBox->buildUserAvailableTasksActionsQuery($userId, $taskId);
            $task = $query
                ->where('completed', 1)
                ->first();
            if ($task) {
                $task->update(['completed' => 0]);
                // notification
                Notify::rollbackTask($userId, $taskId);
                $result->allOK();
                $isStageCompleted = $this->isAllTasksCompletedInStage($userId, $task->stage_id);
                $this->setStageCompletedData($result, $task->stage_id, $isStageCompleted);
                dispatch((new UpdateUserScore($userId))->delay(2)->onQueue('jobs'));
                dispatch((new SendTaskRolledBackEmail($taskId))->delay(3)->onQueue('emails'));
            } else {
                $result->error404();
            }
        }
        return $result;
    }
    protected function setStageCompletedData(ActionResult $result, $stageId, $isCompleted)
    {
        if ($isCompleted) {
            $result->setData('stage_completed', $stageId);
            $result->setData('stage_uncompleted', '');
        } else {
            $result->setData('stage_completed', '');
            $result->setData('stage_uncompleted', $stageId);
        }
    }
    /**
     * return only Users
     */
    public function getInvolvedUsers($taskId)
    {
        /**
         * @var Task $task
         */
        $task = Task::find($taskId);
        $usersIds = [];
        if ($task) {
            $usersIds[] = $task->user_id;
            if (!empty($task->assigneeTeamMember->user_id)) {
                $usersIds[] = $task->assigneeTeamMember->user_id;
            }
            foreach ($task->taskFollowers as $taskFollower) {
                if (!empty($taskFollower->teamMember->user_id)) {
                    $usersIds[] = $taskFollower->teamMember->user_id;
                }
            }
        }
        return array_unique($usersIds);
    }
    public function isAllTasksCompletedInStage($userId, $stageId)
    {
        $countAllTasks = Task::query()
                             ->where('user_id', $userId)
                             ->where('stage_id', $stageId)
                             ->count();
        $countIncompletedTasks = Task::query()
                                     ->where('user_id', $userId)
                                     ->where('stage_id', $stageId)
                                     ->where('completed', 0)
                                     ->count();
        $stageService = App::make(StageService::class);
        return $stageService->isStageCompleted($countAllTasks, $countIncompletedTasks);
    }
    public function createNewTask($userId)
    {
        $stageService = App::make(StageService::class);
        $task = new Task();
        $task->user_id = $userId;
        $task->priority = Task::DEFAULT_PRIORITY;
        $task->due_date = Carbon::now();
        $task->stage_id = $stageService->getDefaultTaskStage($userId)->id;
        $task->hash = md5(uniqid($userId.time(), true));
        return $task;
    }
    public function editTask($taskId, $userId, $data)
    {
        $result = new ActionResult();
        /** @var Task $task */
        $task = empty($taskId) ? $this->createNewTask($userId) : $this->getUserTask($userId, $taskId);
        if ($task) {
            $validator = Validator::make($data, Task::getValidationRules(), [
                'assignee_team_member_id.required' => 'Task is not assigned',
            ]);
            if ($validator->passes()) {
                $data['due_date'] = Carbon::parse($data['due_date']);
                $data['assignee_team_member_id'] = $this->teamService->createTeamMembersIfNeeded($userId, $data['assignee_team_member_id'], 'task');
                $followers = empty($data['followers']) ? [] : $this->teamService->createTeamMembersIfNeeded($userId, $data['followers'], 'task');
                $media = (empty($taskId) && !empty($data['media'])) ? $data['media'] : [];
                unset($data['followers'], $data['media']);
                $needToSendAssigneeEmail = ($data['assignee_team_member_id'] && $task->assignee_team_member_id != $data['assignee_team_member_id']);
                $newFolowers = Collection::make($followers)->diff($task->taskFollowers->pluck('team_member_id'))->values()->toArray();
                $task->fill($data);
                $task->save();
                /**
                 * followers:
                 */
                $task->taskFollowers()->delete();
                foreach ($followers as $follower) {
                    $task->taskFollowers()->create(['team_member_id' => $follower]);
                }
                /**
                 * media:
                 */
                if ($media) {
                    $mediaItems = Media::query()
                                       ->whereIn('id', array_values($media))
                                       ->update([
                                           'model_type' => get_class($task),
                                           'model_id'   => $task->id,
                                       ]);
                    //$task->media()->saveMany($mediaItems);
                }
                if (empty($taskId)) {
                    Notify::createTask($userId, $task->id);
                } else {
                    Notify::editTask($userId, $task->id);
                }
                if ($needToSendAssigneeEmail) {
                    dispatch((new SendTaskAssignedEmail($task->id))->delay(3)->onQueue('emails'));
                }
                if ($newFolowers) {
                    dispatch((new SendTaskFollowersEmail($task->id, $newFolowers))->delay(3)->onQueue('emails'));
                }
                $result->allOK();
                $result->setData('task_id', $task->id);
                $result->setData('property_id', $task->property_id);
            } else {
                $messages = $validator->messages()->toArray();
                $result->setValidation($messages);
            }
        } else {
            $result->error404();
        }
        return $result;
    }
    public function deleteTask($taskId, $userId)
    {
        $result = new ActionResult();
        $task = $this->getUserTask($userId, $taskId);
        if ($task) {
            $task->delete();
            dispatch((new UpdateUserScore($userId))->delay(2)->onQueue('jobs'));
            $result->allOK();
        } else {
            $result->error404();
        }
        return $result;
    }
    public function updateDueDate($taskId, $userId, $date)
    {
        $result = new ActionResult();
        $query = $this->teamBox->buildUserAvailableTasksActionsQuery($userId, $taskId);
        $task = $query->first();
        if ($task) {
            $task->update(['due_date' => Carbon::parse($date)]);
            $result->allOK();
            dispatch((new SendTaskDelayedEmail($taskId, $userId))->delay(3)->onQueue('emails'));
        } else {
            $result->error404();
        }
        return $result;
    }
    public function acceptTaskByUser($userId, $taskId)
    {
        $result = new ActionResult();
        $query = $this->teamBox->buildUserAvailableTasksActionsQuery($userId, $taskId);
        $task = $query
            ->where('assign_status', Task::ASSIGN_STATUS_PENDING)
            ->first();
        if ($task) {
            $task->update(['assign_status' => Task::ASSIGN_STATUS_ACCEPTED]);
            dispatch((new SendTaskAcceptedEmail($taskId))->delay(3)->onQueue('emails'));
            $result->allOK();
        } else {
            $result->error404();
        }
        return $result;
    }
    public function rejectTaskByUser($userId, $taskId)
    {
        $result = new ActionResult();
        $query = $this->teamBox->buildUserAvailableTasksActionsQuery($userId, $taskId);
        $task = $query
            ->where('tasks.id', $taskId)
            ->where('assign_status', Task::ASSIGN_STATUS_PENDING)
            ->first();
        if ($task) {
            dispatch((new SendTaskRejectedEmail($taskId, $userId))->delay(3)->onQueue('emails'));
            $task->update([
                'assign_status'           => Task::ASSIGN_STATUS_REJECTED,
                'assignee_team_member_id' => null,
            ]);
            $result->allOK();
        } else {
            $result->error404();
        }
        return $result;
    }
    public function emailTasksWhereTeamMemberInvolved($teamMemberId)
    {
        /**
         * Assigned:
         */
        $tasks = Task::query()
                     ->where('assignee_team_member_id', $teamMemberId)
                     ->get();
        foreach ($tasks as $task) {
            dispatch((new SendTaskAssignedEmail($task->id))->delay(3)->onQueue('emails'));
        }
        /**
         * Follower:
         */
        $tasks = Task::query()
                     ->select('tasks.*')
                     ->leftJoin('task_followers as tf', 'tf.task_id', '=', 'tasks.id')
                     ->where('tf.team_member_id', $teamMemberId)
                     ->groupBy('tasks.id')
                     ->get();
        foreach ($tasks as $task) {
            dispatch((new SendTaskFollowersEmail($task->id, [$teamMemberId]))->delay(3)->onQueue('emails'));
        }
    }
    public function acceptTaskByHash($hash)
    {
        $result = new ActionResult();
        $task = $this->getTaskByHash($hash);
        if ($task) {
            $result->setData('task', $task);
            $acceptResult = $this->acceptTaskByUser($task->assigneeTeamMember->user_id, $task->id);
            if ($acceptResult->success) {
                $result->allOK();
            }
        } else {
            $result->error404();
        }
        return $result;
    }
    public function rejectTaskByHash($hash)
    {
        $result = new ActionResult();
        $task = $this->getTaskByHash($hash);
        if ($task) {
            $result->setData('task', $task);
            $rejectResult = $this->rejectTaskByUser($task->assigneeTeamMember->user_id, $task->id);
            if ($rejectResult->success) {
                $result->allOK();
            }
        } else {
            $result->error404();
        }
        return $result;
    }
    public function completeTaskByHash($hash)
    {
        $result = new ActionResult();
        $task = $this->getTaskByHash($hash);
        if ($task) {
            $result->setData('task', $task);
            $subResult = $this->completeTaskById($task->assigneeTeamMember->user_id, $task->id);
            if ($subResult->success) {
                $result->allOK();
            }
        } else {
            $result->error404();
        }
        return $result;
    }
}
