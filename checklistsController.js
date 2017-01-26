angular.module('checklistModule')
    .controller('ChecklistsController',
    function ($scope, $http, $location, $compile, $rootScope, $timeout, $log, growl, $q, StepService,
        dataFactory, API, helperService, ipCookie, blockUI, $window, $document, UtilFactory) {

        if (typeof checklistId != "undefined") {
            console.debug("ChecklistsController-Onload: checklistId", checklistId);
        }
        /* Javascript vars setup by server */
        $scope.checklistSettings = {
            conditionIndex: '',
            toggle: function (panelName) {
                if ($scope.isLocked) {
                    return;
                }
                for (panel in this.panels) {
                    (panel == panelName) ? (this.panels[panel] = !this.panels[panel]) : (this.panels[panel] = true);
                }
            },
            panels: {
                settings: true,
                power_builder: true,
                conditionals: true
            }
        };

        var USER_ID = user_id;
        var CHECKLIST_ID;
        if (typeof checklistId != "undefined") {
            CHECKLIST_ID = checklistId;
        }
        var SKIP_NEW_CHECKLIST_TOUR = skipNewChecklistTour;
        var SKIP_EDIT_CHECKLIST_TOUR = skipEditChecklistTour;
        var isLocked = $scope.isLocked = typeof $window.checklist !== 'undefined' && $window.checklist.is_lock == true && $window.checklist.lock_by != user_id;
        //For Dev purposes
        //SKIP_NEW_CHECKLIST_TOUR = false;
        //SKIP_EDIT_CHECKLIST_TOUR = false;

        console.info('skipNewChecklistTour', skipNewChecklistTour);
        console.info('skipEditChecklistTour', skipEditChecklistTour);

        /* =============== Local Vars =============== */
        $scope.newStepPosition;
        $scope.saveAndViewChecklistBtn = {};
        $scope.saveAndViewChecklistBtn.updateStepBeforeRedirect = false;
        $scope.userTimezone = $window.userTimezone;
        $scope.isAdmin = $window.is_admin;
        $scope.logins = parseInt($window.logins);
        $scope.tags_permissions = $window.tags_permissions;
        $scope.parseMarkDown = UtilFactory.parseMarkDown;
        $scope.currentOrganization = $window.currentOrganization;
        $scope.stepOptions = {
            isNewStep: false,
            currentStep: {},
            step: {
                selectedStep: {},
                selectedStepIndex: null
            }
        };

        /**
         * checklistSettings
         * manage checklist advanced settings tabs[settings|power builder|conditional]
         * Only one panel is shown at once
         */

        $scope.checklistSettings.conditionIndex = -1;
        /**
         * user voice widget configurations
         * type[lightbox,tab,default] is mendatory to show widget.
         * set globaloptions to override global properties
         * set localOptions to apply properties to current widget
         * @type {Object}
         */
        $scope.userVoice = {
            type: 'lightbox',
            globalOptions: {},
            localOptions: {
                mode: 'full',
                primary_color: '#cc6d00',
                link_color: '#007dbf',
                default_mode: 'support',
                forum_id: 181582
            }
        };

        /* =============== Loaders =============== */
        var blockableStepsListUI = blockUI.instances.get('blockableStepsListUI');
        var blockableChecklistUI = blockUI.instances.get('blockableChecklistUI');
        $scope.advancedChecklistShow = false;
        var checklist = {
            id: CHECKLIST_ID,
            title: '',
            summary: '',
            user_id: USER_ID,
            privacy: 'organization',
            steps: [],
            settings: {
                record_rating: 'no'
            }
        };

        $scope.tags = {
            all: $window.tags,
            selected: $window.checklist ? $window.checklist.tags.data : []
        };

        $scope.summaryMaxChars = 2000;
        $scope.powerBuilderMaxChars = 2000;

        if (typeof checklistPermissions != "undefined") {
            $scope.permissions = checklistPermissions;
        }

        $scope.$watch('conditionals.condition.step_id', function (n, o) {
            if (n != o) {
                $($scope.checklist.steps.data).each(function (key, data) {
                    if (n == data.id) {
                        $scope.conditionals.methods.setCapturedFields(data);
                    }
                });
            }
        }, true);

        //Auto update Public radio
        $scope.$watch('checklist.settings.is_public', function (n, o) {
            if (n != o && n != null) {
                $scope.saveChecklistAdvanced();
            }
        }, true);

        $scope.conditionals = {
            isEditable: false,
            list: [],
            condition: {
                name: '',
                step_id: null,
                capture_id: null,
                tags: [],
                keyword: '',
                action: '',
                step_action: '',
                steps: []
            },
            actions: [{
                key: 'hide-tags',
                title: 'Hide steps tagged with'
            }, {
                    key: 'unhide-tags',
                    title: 'Unhide steps tagged with'
                }, {
                    key: 'hide-steps',
                    title: 'Hide steps'
                }, {
                    key: 'unhide-steps',
                    title: 'Unhide steps'
                }],
            selectedStep: {},
            selectedStepCaptures: [],
            methods: {
                updateConditionStatus: updateConditionStatus,
                editCondition: editCondition,
                deleteCondition: deleteCondition,
                saveCondition: saveCondition,
                setCapturedFields: function ($item, $model) {
                    angular.extend($scope.conditionals.selectedStep, $item);
                    $scope.conditionals.selectedStepCaptures.length = 0;
                    _.each($scope.conditionals.selectedStep.metadata, function (meta, propertyId) {
                        if (meta.hasOwnProperty('type')) {
                            $scope.conditionals.selectedStepCaptures.push({
                                'propertyId': propertyId,
                                'property': meta
                            });
                        }
                    });
                    if ($scope.conditionals.selectedStepCaptures.length === 0) {
                        $scope.conditionals.condition.capture_id = null;
                    }
                },
                resetForm: function () {
                    angular.extend($scope.conditionals.condition, {
                        name: '',
                        step_id: null,
                        capture_id: null,
                        tags: [],
                        keyword: '',
                        id: null,
                        action: '',
                        step_action: '',
                        steps: []
                    });

                    $scope.checklistSettings.conditionIndex = -1;

                    $scope.conditionals.selectedStepCaptures.length = 0;
                }
            },
            sortableOptions: {
                handle: '.sortHandle2',
                axis: 'y',
                update: function (event, ui) {
                    if ($scope.conditionals.condition.id) {
                        growl.error('Please save edited condition before re-ordering.');
                        ui.item.sortable.cancel();
                    }
                },
                stop: function (event, ui) {
                    if ($scope.conditionals.condition.id) {
                        return;
                    }
                    // Check whether drop index is valid and is not out of the range
                    if (ui.item.sortable.dropindex >= 0 && ui.item.sortable.dropindex < $scope.conditionals.list.length) {
                        for (var i = 0; i < $scope.conditionals.list.length; i++) {
                            $scope.conditionals.list[i].position = i + 1;
                        }
                        $scope.conditionals.methods.saveCondition($scope.conditionals.list[ui.item.sortable.dropindex]);
                    }
                }
            }
        };

        $scope.sortableOptions = {
            handle: '.sortHandle',
            axis: 'y',
            stop: function (event, ui) { // After swapping this function is called
                console.info(event, ui);
                console.log(ui.item.sortable.index, $scope.checklist.steps.data[ui.item.sortable.index])

                // Check whether drop index is valid and is not out of the range
                if (ui.item.sortable.dropindex >= 0 &&
                    ui.item.sortable.dropindex < $scope.checklist.steps.data.length) {
                    $scope.reorderSteps();
                    $scope.savePosition($scope.checklist.steps.data[ui.item.sortable.dropindex]);
                }
            }
        };

        /* Step Controller Default Vars - Start */
        var stepStub = {
            title: null,
            summary: null,
            position: null,
            metadata: []
        };

        $scope.activeStep = StepService.activeStep;
        $scope.selectedStep = angular.copy(stepStub);
        $scope.stepPosition = 1;
        /* Step Controller Default Vars - End */

        var stateENUM = {
            CREATE: "CREATE",
            UPDATE: "UPDATE"
        };
        $scope.state = stateENUM.CREATE;
        if (window.location.pathname.indexOf('edit') != -1)
            $scope.state = stateENUM.UPDATE;

        $scope.creatingChecklist = false; //Avoid duplicate checklist creation

        // ================ INIT ================
        // load cookie, or start new tour

        $scope.disableChecklistTour = false;

        console.info('SKIP_NEW_CHECKLIST_TOUR', SKIP_NEW_CHECKLIST_TOUR);
        console.info('SKIP_EDIT_CHECKLIST_TOUR', SKIP_EDIT_CHECKLIST_TOUR);

        if (SKIP_NEW_CHECKLIST_TOUR && SKIP_EDIT_CHECKLIST_TOUR)
            $scope.disableChecklistTour = true;
        else if (SKIP_NEW_CHECKLIST_TOUR && !SKIP_EDIT_CHECKLIST_TOUR) //Edit Tour
            $scope.createChecklistTourStep = 2;
        else if ($scope.state == stateENUM.CREATE)
            $scope.createChecklistTourStep = 0; //New tour
        else
            $scope.disableChecklistTour = true;
        if (!SKIP_NEW_CHECKLIST_TOUR && CHECKLIST_ID == null) {
            window.setTimeout(function () {
                $("#HelpModal").modal('show');
            }, 0500);
        }

        //ipCookie('myNewChecklistTour') this cookie saves the active tour step #

        $scope.adjustRemainingSummaryChars = function () {
            var desc = $scope.checklist.summary;
            var maxChars = $scope.summaryMaxChars;

            if (desc && desc.length >= maxChars) {
                $scope.remainingCharacters = 0;
                $scope.checklist.summary = $scope.checklist.summary.substring(0, maxChars);
                return false;
            }

            if (desc) {
                var newLines = desc.match(/(\r\n|\n|\r)/g);
                var addition = 0;
                if (newLines != null) {
                    addition = newLines.length;
                    maxChars = maxChars * 1 + addition * 1;
                }
            }

            $scope.checklist.summary = $scope.checklist.summary.substring(0, maxChars);
            jQuery("#description").attr('maxlength', maxChars);

            $scope.remainingCharacters = maxChars - $scope.checklist.summary.replace("\r\n", "").length;
            $scope.remainingCharacters = $scope.remainingCharacters - ($scope.checklist.summary.split(/[\r\n]/).length - 1);
        };


        $scope.tourStepComplete = function () {
            console.info('tourStepComplete', $scope.createChecklistTourStep);

            // If in create mode and tour reached its end, update the new checklist flag
            if ($scope.state == stateENUM.CREATE &&
                ($scope.createChecklistTourStep == 0 ||
                    $scope.createChecklistTourStep == 1)
            ) {
                $rootScope.updateTourFlag('skip_new_checklist_tour');
            } else if ($scope.state == stateENUM.UPDATE && $scope.createChecklistTourStep > 1) {
                $rootScope.updateTourFlag('skip_edit_checklist_tour');
            }

            // save cookie after each step
            //ipCookie('myNewChecklistTour', $scope.currentTourStep, { expires: 3000 });
        };

        if (helperService.isEmpty(CHECKLIST_ID)) {
            //New Checklist
            $scope.checklist = angular.copy(checklist);
            $scope.stepPosition = 1;
            $scope.state = stateENUM.CREATE;
            updateCheckListData();

        } else {
            $scope.state = stateENUM.UPDATE;
            $scope.checklist = {};

            //Get existing checklist
            blockableChecklistUI.start();
            API.Checklist.getChecklist(checklist.id).then(
                function successCallback(response) {
                    $scope.checklist = response.data;

                    console.warn("------- $scope.checklist", $scope.checklist);
                    if ($scope.checklist.steps.data) {
                        $scope.stepPosition = $scope.checklist.steps.data.length + 1;
                        $scope.newStepPosition = $scope.checklist.steps.data.length + 1;
                    } else {
                        $scope.checklist.steps.data = [];
                        $scope.stepPosition = 1;
                        $scope.newStepPosition = 1;
                    }
                    if (typeof $scope.checklist.settings.allow_guests_to_track == 'undefined') {
                        $scope.checklist.settings.allow_guests_to_track = 'yes';
                        $scope.saveChecklistAdvanced(false);
                    }
                    ($scope.checklist.allowing_guests == 1) ? $scope.checklist.allowing_guests = 'no' : $scope.checklist.allowing_guests = 'yes';
                    $scope.selectedStep.position = parseInt($scope.stepPosition);
                    console.debug("stepPosition-", $scope.stepPosition);

                    $scope.adjustRemainingSummaryChars();
                    updateCheckListData();

                    // picked up by "fieldEditController" to load current checklist prerun fields
                    $rootScope.$broadcast('checklist:prerun:loaded');

                    blockableChecklistUI.stop();
                },
                function errorCallback() {
                    //handle error
                    blockableChecklistUI.stop();
                }
            );
        }

        function updateCheckListData() {
            if (!$scope.checklist.steps.data) {
                return;
            }
            _.each($scope.checklist.steps.data, function (step) {
                if (!step.metadata) {
                    return;
                }
                for (var key in step.metadata) {
                    if (step.metadata[key]['type'] == 'field') {
                        step.metadata[key]['position'] = parseInt(step.metadata[key]['position']);
                    }
                }

            });

        }

        function validateChecklist() {
            var valid = true;

            valid = !helperService.isEmpty($scope.checklist.title);

            return valid;
        }

        // ================ ng-click handlers - Checklist ================
        /**
         * Create a new checklist on Save
         */
        $scope.createChecklist = function (redirect) {
            if ($scope.state == stateENUM.CREATE && !$scope.creatingChecklist) {
                var valid = validateChecklist();

                if (!valid)
                    return false;

                console.warn("Creating Process");
                growl.info("Creating process...");

                $scope.creatingChecklist = true;
                blockableChecklistUI.start();
                var checklist = angular.copy($scope.checklist);
                angular.extend(checklist.settings, {
                    allow_steps_editing: 'yes'
                });

                API.Checklist.createChecklist(checklist).then(
                    function successCallback(response) {
                        var newChecklist = response.data;
                        //$scope.creatingChecklist = false;
                        //$scope.state = stateENUM.UPDATE;

                        if (redirect) //Take user to the checklist View page
                            window.location = '/checklists/' + newChecklist.id;
                        else {
                            window.location = '/checklists/' + newChecklist.id + '/edit'
                        }
                        blockableChecklistUI.stop();
                    },
                    function errorCallback() {
                        //handle error
                        blockableChecklistUI.stop();
                    }
                );
            }
        };
        /**
         * importSteps
         * create steps in bulk
         *
         * @author Mohan Singh <mslogicmaster@gmail.com>
         * @return void
         */
        $scope.importSteps = function () {

            if (!$scope.permissions.can_create_steps) {
                growl.error("You don't have permissions to create steps");
                return;
            }

            if ($rootScope.isStepEditMode) {
                growl.info('Step in progress. Please finish current step before importing.');
                return;
            }
            var checklist = angular.copy($scope.checklist),
                steps = prepareSteps($scope.checklist.powerBuild);

            //for the update process set the checklist steps only to the ones just added through the powerbuilder
            checklist.steps.data = steps;
            checklist.steps.data = removeDeadlineRules(checklist.steps.data);

            if (checklist.hasOwnProperty('powerBuild')) {
                delete checklist.powerBuild;
            }
            blockableChecklistUI.start();
            API.Checklist.importSteps(checklist.id, steps)
                .then(function (steps) {
                    API.Step.getStep(checklist.id)
                        .then(function (checkListWithSteps) {
                            //update checklist steps data
                            $scope.checklist.steps.data = checkListWithSteps.data.steps.data;
                            //Increase add step position by count of imported steps
                            $scope.newStepPosition = checkListWithSteps.data.steps.data.length + 1;
                            $scope.checklist.powerBuild = '';
                            blockableChecklistUI.stop();
                            growl.success("Steps have been imported successfully");
                        });
                },
                function () {
                    blockableChecklistUI.stop();
                });
        };
        /**
         * prepareSteps
         * a helper function to create step stub
         *
         * @author Mohan Singh <mslogicmaster@gmail.com>
         * @param  {string} stepsString A string of steps seprated by \n(new line)
         * @return {array}             Array of steps with default properties
         */
        function prepareSteps(stepsString) {
            var stepsTitle = stepsString.split('\n'),
                steps = [];
            if (!stepsTitle.length) {
                return;
            }
            _.each(stepsTitle, function (title) {
                var step = angular.copy(StepService.defaultStepSchema);
                angular.extend(step, {
                    title: title,
                    checklist_id: $scope.checklist.id,
                    owners: {
                        users: StepService.getDefaultOwners()
                    }
                });
                steps.push(step);
            });
            return steps;
        }

        $scope.showExplorer = function () {
            $scope.explorerShow = !$scope.explorerShow;
            ($scope.explorerShow) ? jQuery('#explorerBox').slideDown() : jQuery('#explorerBox').slideUp();
        }

        $scope.showPrerunFields = function () {
            $scope.prerunFieldsShow = !$scope.prerunFieldsShow;
            ($scope.prerunFieldsShow) ? jQuery('#prerunFieldsBox').slideDown() : jQuery('#prerunFieldsBox').slideUp();
        }

        $scope.saveChecklistAdvanced = function (showGrowl) {

            if (!$scope.permissions.can_edit_checklist) {
                growl.error("You don't have permissions to update checklist");
                return;
            }

            showgrowl = typeof showGrowl !== 'undefined' ? showGrowl : true;
            console.debug($scope.checklist);
            //cast the settings information to object
            //$scope.checklist.settings = _.extend({}, {'settings' : $scope.checklist.settings.settings} || {});
            $scope.checklist.settings = _.extend({}, $scope.checklist.settings || {});

            console.log('settings: ');
            console.log($scope.checklist.settings);

            if ($scope.checklist.settings.allow_guests_to_track === 'no') {
                angular.forEach($scope.checklist.steps.data, function (step, stepKey) {
                    $scope.checklist.steps.data[stepKey].metadata.allow_guest_owners = 0;
                });
            }

            var checklist = angular.copy($scope.checklist);
            checklist.steps.data = removeDeadlineRules(checklist.steps.data);

            API.Checklist.updateChecklist($scope.checklist.id, checklist).then(
                function successCallback(response) {
                    if ($scope.checklist.settings == 'null')
                        $scope.checklist.settings = null;

                    if (showGrowl) {
                        growl.success("Process '" + $scope.checklist.title + "' updated.");
                    }

                    console.warn("Process updated", response);
                    console.log('$scope.checklist.steps.data SUCCESS: ');
                    console.log($scope.checklist.steps.data);

                },
                function errorCallback() {
                    //handle error
                }
            );
        }


        /**
         * Auto update checklist when user changes form fields (title/summary)
         */
        $scope.updateChecklist = function (redirect) {

            if (!$scope.permissions.can_edit_checklist) {
                growl.error("You don't have permissions to update checklist");
                return;
            }

            if (!isValidURL($scope.checklist.settings.webhook)) {
                growl.error("Invalid URL value entered in the Webhook field");
                return;
            }

            if ($scope.state == stateENUM.UPDATE) {
                var valid = validateChecklist();

                if (!valid) {
                    growl.error("Please enter a process title");
                    return false;
                }

                console.warn("Updating Process");
                var checklist = angular.copy($scope.checklist);
                checklist.steps.data = removeDeadlineRules(checklist.steps.data);
                if (!redirect) {
                    checklist.keep_lock = true;
                }
                blockableChecklistUI.start();
                API.Checklist.updateChecklist($scope.checklist.id, checklist).then(
                    function successCallback(response) {
                        growl.success("Process '" + $scope.checklist.title + "' updated.");

                        console.warn("Process updated", response);

                        $scope.saveAndViewChecklistBtn.clicked = false;
                        //Take user to the checklist View page
                        if (redirect) {
                            window.location = '/checklists/' + $scope.checklist.id;
                        } else {
                            blockableChecklistUI.stop();
                        }
                    },
                    function errorCallback() {
                        //handle error
                        blockableChecklistUI.stop();
                    }
                );
            }
        };

        function removeDeadlineRules(steps) {
            var tempSteps = angular.copy(steps);
            _.each(tempSteps, function (step) {
                if (step.hasOwnProperty('deadline_rules')) {
                    delete step.deadline_rules;
                }
            })
            return tempSteps;
        }

        /**
         * Process ng-click on "Save and view checklist" button
         * @keyEvent            event       used to determine if the user pressed enter in the input field
         * @param redirect      boolean     true: On create, redirects the user to the view page. false: updates the page url.
         */
        $scope.saveChecklist = function (keyEvent, redirect, checklistTitle) {

            //if the checklist title is edited in place, the checklist title is passed to the saveChecklist function
            checklistTitle = typeof checklistTitle !== 'undefined' ? checklistTitle : '';
            if (checklistTitle) {
                $scope.checklist.title = checklistTitle;
            }

            if (keyEvent && keyEvent.which) {
                if (keyEvent.which !== 13) { //Did not pressed enter?
                    return false; //exit, else continue
                }
            }

            if ($scope.state == stateENUM.CREATE)
                $scope.createChecklist(redirect);
            else
                $scope.updateChecklist(redirect);
        };
        $scope.exportChecklist = function () {
            window.location.assign('/checklists' + CHECKLIST_ID + '/export');
        };

        // ================ ng-click handlers - Step ================
        /**
         * Clears current select selected step and resets form for a new step addition
         */
        function showNewStepForm() {
            $scope.stepEdit = false;
            $scope.selectedStep = angular.copy(stepStub);
            if ($scope.checklist.steps.data)
                $scope.stepPosition = $scope.checklist.steps.data.length + 1;
            else
                $scope.stepPosition = 1;
            $scope.selectedStep.position = $scope.stepPosition;
            $scope.stepOptions.isNewStep = false;
        }

        /**
         * On event: checklist:step:added, append to the current step array
         * Event From: stepEditBox.js -> createStep
         * @param newStepEditor confirms step is created via the New Step Editor box
         */
        $rootScope.$on('checklist:step:created', function (event, newStep, newStepEditor) {
            if (!$scope.checklist.steps.data)
                $scope.checklist.steps.data = [];

            console.debug('on checklist:step:created', newStep);

            $scope.checklist.steps.data.push(newStep);
            //$scope.selectedStep = newStep;

            $scope.newStepPosition++;
            $scope.stepOptions.isNewStep = false;
            //showNewStepForm();
        });

        /**
         * On event: checklist:step:deleted, slice from current step array
         * Event From: stepEditController.js -> deleteStep
         */
        $rootScope.$on('checklist:step:deleted', function (event, deletedStep) {
            if (deletedStep) {

                console.debug('on checklist:step:deleted', deletedStep);

                for (var i = 0; i < $scope.checklist.steps.data.length; i++) {
                    if ($scope.checklist.steps.data[i].id == deletedStep.id) {
                        $scope.checklist.steps.data.splice(i, 1);
                    }
                }

                $scope.reorderSteps();
                $scope.newStepPosition--;

                var last = $scope.checklist.steps.data.length - 1;
                if( last >= 0 ) {
                    if( $scope.checklist.steps.data[ last ].metadata.deadline.step == 'last_step' ) {
                        $scope.checklist.steps.data[ last ].metadata.deadline.step = 'start_run';
                        if( $scope.checklist.steps.data[ last ].metadata.deadline.option == 'prior_to' ) {
                            $scope.checklist.steps.data[ last ].metadata.deadline.option = 'from';
                        }
                    }
                }
            }
        });

        /**
         * On event: checklist:step:added, append to the current step array
         * Event From: stepEditBox.js -> saveStep
         *
         * @param newStepEditor confirms step is created via the New Step Editor box
         */
        $rootScope.$on('checklist:step:updated', function (event, updatedStep, newStepEditor, stepToOpen) {
            console.log('on checklist:step:updated', updatedStep);
            //Get step object from the checklist array
            var presentObj = $scope.checklist.steps.data.filter(function (element) {
                return element.id == updatedStep.id;
            })
            console.log('on checklist:step:updated presentObj', presentObj);

            //Update the step in the checklist array
            if (presentObj.length == 1) {
                $scope.checklist.steps.data[$scope.checklist.steps.data.indexOf(presentObj[0])] = updatedStep;
            } else
                $scope.checklist.steps.data.push(updatedStep); //Just created so add it to the checklist array
            //Increment new step position if coming form the New Step Editor box
            if (newStepEditor)
                $scope.newStepPosition++;

            //Reset step form
            showNewStepForm();

            //saveAndViewChecklist btn was clicked but there was a step opened for edit so updated it first and now call updateChecklist
            if ($scope.saveAndViewChecklistBtn.clicked == true) {
                $scope.saveChecklist(null, true);
            }

            if (stepToOpen) {
                $rootScope.$emit('EVENT:OPEN_STEP', stepToOpen);
                console.warn('Open step', stepToOpen);
            }


        });

        //if there was no step opened for edit, clicking the "Save And View Checklist" button will broadcast this event so that we can call updateChecklist directly (without first updating any steps)
        $rootScope.$on('saveAndViewChecklistClicked', function (event) {
            console.log('saveAndViewChecklistClicked was broadcasted');
            $scope.saveChecklist(null, true);
        });

        $rootScope.$on('checklist:step:step_data_updated', function ($event, data) {
            $scope.conditionals.methods.setCapturedFields(data.updated_step, null);
        });

        $scope.addStepCallback = function (editing, checklistData) {
            if (editing) {
                $scope.updateStep(checklistData);
            } else {
                $scope.addNewStep(checklistData);
            }
        };

        /* =============== UI Element Helpers ============ */
        /**
         * Class helper, disabls a div if in CREATE mode
         */
        $scope.disableIfCreate = function () {
            return $scope.state == stateENUM.CREATE ? 'disable-div' : '';
        };

        $scope.readonlyIfCreate = function () {
            return $scope.state == stateENUM.CREATE;
        };

        $scope.disableIfUpdate = function () {
            return $scope.state == stateENUM.UPDATE ? 'disable-div' : '';
        }


        /**
         *  Used by ui-sortable to reorder steps 1-n in ascending order after a user
         *  changes the order of the steps
         */
        $scope.reorderSteps = function () {
            var deferred = $q.defer();
            var calls = [];

            console.debug('at reorderSteps');
            //Reset position of all steps 1-n
            for (var i = 0; i < $scope.checklist.steps.data.length; i++) {
                $scope.checklist.steps.data[i].position = i + 1;
            }
        };

        /**
         *   Update the step position via the API
         */
        $scope.savePosition = function (step) {
            console.debug('savingPosition', step.id, step.position);
            blockableStepsListUI.start();
            API.Step.updateStepPosition(step.id, step.position).then(
                function successCallback(response) {
                    $rootScope.$emit('checklist:step:step_data_updated', {
                        updated_step: $scope.selectedStep
                    });
                    growl.success("Step '" + response.data.title + "' updated.");

                    blockableStepsListUI.stop();
                },
                function errorCallback() {
                    //handle error
                    blockableStepsListUI.stop();
                }
            );
        };

        /**
         * invoke function to start capturing events
         */
        //listenOwnerUpdateEvent();
        /**
         * listenOwnerUpdateEvent
         * listens event emitted from stepEditController
         * @see  stepEditController.js
         * When owner is removed/selected from multiple select, owner does not update is checklist object
         * So this code going to keep owners object updated in checlist object.
         *
         * keep in mind, Once event is listen , that should be remored from the scope when $scope is destroyed
         * @return {[type]} [description]
         */
        function listenOwnerUpdateEvent() {
            var ownerUpdateEvent = $rootScope.$on('checklist:step:owners_updated', function (event, stepData) {
                updateStepOwners(stepData.stepId, stepData.owners);
            });
            // $scope $destroy
            $scope.$on('$destroy', ownerUpdateEvent);
        }
        /**
         * updateStepOwners
         * A helper function to update owners in checklst object
         * @param  {integer} stepId A step ID of selected step
         * @param  {array} owners An array of owner's id
         * @return {void}
         */
        function updateStepOwners(stepId, owners) {
            var users = [];
            users = _.map(owners, function (owner) {
                return owner.id;
            });

            _.each($scope.checklist.steps.data, function (step) {
                if (stepId === step.id) {
                    step.owners = {
                        users: users
                    };
                    return;
                }
            });
        }

        /**
         * addDefaultData
         * Adds default owners and deadline
         *
         * @see StepService.defaultDeadLine
         * @see $scope.importSteps for usage
         *
         * @author Mohan Singh <mslogicmaster@gmail.com>
         * @param  {[type]} steps An array of steps
         * @return Array  An array of steps with default deadline and owners
         */
        function addDefaultData(steps) {
            if (!steps && !steps.length) {
                return steps;
            }
            var tempSteps = angular.copy(steps);
            _.each(tempSteps, function (step) {
                if (step.hasOwnProperty('metadata')) {
                    step.metadata.deadline = StepService.defaultDeadLine;
                }
                if (!step.hasOwnProperty('owners')) {
                    angular.extend(step, {
                        owners: {
                            users: StepService.getDefaultOwners()
                        }
                    });
                }
            })
            return tempSteps;
        }

        function isValidURL(field) {
            if (!field) {
                return true;
            }
            var urlPattern = /(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w\-.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
            return urlPattern.test(field);
        }
        if (CHECKLIST_ID) {
            loadConditions();
        }

        function loadConditions() {
            API.Condition.getConditions(checklist).then(
                function successCallback(response) {
                    var list = response.data;
                    list = _.sortBy(list, 'position');
                    $scope.conditionals.list = list;
                },
                function errorCallback() { }
            );
        }

        function saveCondition(conditionWithPosition) {
            var restResource, condition;

            condition = conditionWithPosition ? angular.copy(conditionWithPosition) : angular.copy($scope.conditionals.condition);

            if (!isConditionalValid(condition)) {
                growl.error('All fields are required to create condition.');
                return;
            }

            $scope.checklistSettings.conditionIndex = -1;

            blockableChecklistUI.start();

            switch (condition.tags_or_steps) {
                case 'unhide-tags':
                    condition.action = 'unhide';
                    condition.step_action = '';
                    condition.steps = [];
                    break;
                case 'hide-tags':
                    condition.action = 'hide';
                    condition.step_action = '';
                    condition.steps = [];
                    break;
                case 'hide-steps':
                    condition.step_action = 'hide';
                    condition.action = '';
                    condition.tags = [];
                    break;
                case 'unhide-steps':
                    condition.step_action = 'unhide';
                    condition.action = '';
                    condition.tags = [];
                    break;
            }

            restResource = condition.id ? API.Condition.updateCondition(checklist, condition.id, condition) : API.Condition.createCondition(checklist, condition);

            restResource.then(
                function successCallback(response) {
                    var growlMessage = 'Condition has been ' + (condition.id ? 'updated' : 'added') + ' successfully.',
                        newCondition;
                    if (condition.id) {
                        for (var i = 0; i < $scope.conditionals.list.length; i++) {
                            if ($scope.conditionals.list[i]['id'] == response.data.id) {
                                angular.extend($scope.conditionals.list[i], response.data);
                                break;
                            }
                        }
                    } else {
                        newCondition = angular.copy(response.data);
                        newCondition.is_active = 1;
                        $scope.conditionals.list.push(newCondition);
                    }
                    growl.success(growlMessage);
                    blockableChecklistUI.stop();
                    if (conditionWithPosition) {
                        return;
                    }
                    $scope.conditionals.methods.resetForm();
                    $timeout(function () {
                        $document.scrollToElement(angular.element(document.getElementById('condition_' + (condition.id || newCondition.id))), 56, 1000);
                    }, 100);

                },
                function errorCallback(response) {
                    blockableChecklistUI.stop();
                    _.each(response.errors, function (key, value) {
                        growl.error(key[0]);
                    });
                }
            );
        }

        function isConditionalValid(conditionObj) {
            var condition = conditionObj;
            return (condition.name && condition.step_id && condition.capture_id && (condition.tags.length || condition.steps.length) && condition.keyword);
        }

        function deleteCondition(condition) {
            blockableChecklistUI.start();
            API.Condition.deleteCondition(checklist, condition.id).then(
                function successCallback(response) {
                    blockableChecklistUI.stop();
                    growl.success("Condition has been deleted successfully.");
                    for (var i = 0, len = $scope.conditionals.list.length; i < len; i++) {
                        if ($scope.conditionals.list[i].id == condition.id) {
                            $scope.conditionals.list.splice(i, 1);
                            repositionConditions();
                            return;
                        }
                    }
                },
                function errorCallback() {
                    //handle error
                    blockableChecklistUI.stop();
                }
            );
        }
        /**
         * repositionConditions
         * reposition conditions after condition has deleted
         *
         * @author Mohan Singh <mslogicmaster@gmail.com>
         * @return {void}
         */
        function repositionConditions() {
            for (var i = 0; i < $scope.conditionals.list.length; i++) {
                $scope.conditionals.list[i].position = i + 1;
            }
        }

        function editCondition(condition, index) {
            condition.tags_or_steps = (condition.action == 'hide') ? 'hide-tags' : 'unhide-tags';
            if (condition.step_action == 'hide' || condition.step_action == 'unhide') {
                condition.tags_or_steps = (condition.step_action == 'hide') ? 'hide-steps' : 'unhide-steps';
            }
            angular.extend($scope.conditionals.condition, condition);
            $document.scrollToElement(angular.element(document.getElementById('conditionals_section')), 56, 1000);

            $scope.checklistSettings.conditionIndex = index;

        }

        function updateConditionStatus(condition, status) {
            blockableChecklistUI.start();
            var data = {
                'is_active': (status == 1) ? 0 : 1
            };
            API.Condition.updateCondition(checklist, condition.id, data).then(
                function successCallback(response) {
                    loadConditions();
                    growl.success("Condition has been disabled successfully.");
                    blockableChecklistUI.stop();
                },
                function errorCallback() {
                    //handle error
                    blockableChecklistUI.stop();
                }
            )
        }

        $scope.getDefaultDeadline = function (step, timezone) {
            if(step.deadline_string) {
                var deadline = moment.utc(step.deadline_string);
                return StepService.getStepDeadlineInGivenTimezone(deadline, timezone, true, false);
            } else if (step.metadata.deadline) {
                //get default deadline for this task from the step's metadata -> the deadline will be in user's current timezone
                var defaultDeadline = StepService.getStepDeadline(step);
                //convert default deadline in User timezone
                var defaultDeadlineWithTimezone = UtilFactory.deadlineTimezoned(defaultDeadline, timezone);
                defaultDeadlineWithTimezone = moment(moment(defaultDeadlineWithTimezone).format('YYYY-MM-DD HH:mm:00'));
                return StepService.getStepDeadlineInGivenTimezone(defaultDeadlineWithTimezone, timezone, true, false);
            } else {
                return false;
            }
        }


        $scope.import = function (file) {
            if(!file) {
                return;
            }
            blockableChecklistUI.start();
            API.Checklist.import(file).then(function successCallback(response) {
                growl.success('Checklist successfully imported.');
                window.location = '/checklists/' + response.data.id;
            }, function errorCallback(response) {
                blockableChecklistUI.stop();
            })
        }
    });
