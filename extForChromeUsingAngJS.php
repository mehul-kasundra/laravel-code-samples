It's a extension for chrome.This part of code select connections from linkedin,assign for every user tag,export as csv file etc.

function connectionsTableCtrl($scope, $rootScope, $interval, $timeout, $window, apiService, chromeService, connectionsService, roundProgressService, linkedInService) {

    var self = this;
    $scope.refresh_btn = 'Refresh';
    $scope.selected = false;
    $scope.list_of_tags = [];
    $scope.connections = [];
    $rootScope.changeCurrent = 0;
    $scope.selectedTags = {};
    $scope.selectedTags.tags = [];

    this.getTags = function () {
        apiService.getUserData($scope.session_cookie, []).then(
            function (root) {
                $scope.list_of_tags = root.data.content.settings.tags;
            },
            function (error) {
                console.log('Error message');
            }
        )
    };

    this.getUserConnections = function (limit, current, skip) {
        $scope.connections = [];
        apiService.getUserConnections($scope.session_cookie, $scope.selectedTags.tags, limit, current, skip, $scope.keyword).then(
            function (root) {
                var selected;
                $scope.connections = root.data;
                $scope.total = root.total;
                $scope.pagestart = parseInt(root.skip) == 0 ? 1 : parseInt(root.skip);
                $scope.pageend = parseInt(root.limit) > $scope.total ? $scope.total : parseInt(root.limit);
                $scope.current = current;
                self.getTags();

                for (var i = 0; i < $scope.connections.length; i++) {
                    selected = $scope.connections[i].selected;
                    if (selected) break;
                }
                toggleButtons(selected);

            },
            function (error) {
                console.log('Error message');
            }
        )
    };

    $scope.getNextPage = function () {
        if ($scope.pageend == $scope.total) {
            return;
        }

        self.getUserConnections($scope.pageend + 100, $scope.current + 1, $scope.pageend);
    };

    $scope.getPrevPage = function () {
        if ($scope.pagestart == 1) {
            return;
        }
        self.getUserConnections($scope.pagestart, $scope.current - 1, $scope.pagestart - 100);
    };

    $scope.applyFilter = function () {
        self.getUserConnections($scope.pageend, $scope.current, $scope.pagestart);
    };

    $scope.optionToggled = function () {
        var selected;
        $scope.isAllSelected = $scope.connections.every(function (itm) {
            return itm.selected;
        });
        for (var i = 0; i < $scope.connections.length; i++) {
            selected = $scope.connections[i].selected;
            if (selected) break;
        }
        toggleButtons(selected);
    };

    $scope.toggleAll = function () {
        var totalSelected = $scope.connections.length,
            toggleStatus = false,
            isSelected = false;

        for (var i = 0; i < $scope.connections.length; i++) {
            selected = $scope.connections[i].selected;
            if (selected) {
                totalSelected += 1;
                isSelected = true;
            }
        }

        if (!isSelected) {
            toggleStatus = true;
            totalSelected = $scope.connections.length;
        }

        angular.forEach($scope.connections, function (itm) {
            itm.selected = toggleStatus;
        });

        toggleButtons(toggleStatus, totalSelected);
    };

    function toggleButtons(flag, totalSelected) {
        if (flag) {
            $('.btns .toggle-button').removeClass('disabled');
            $('.select-all-toggler').addClass('selected');
            $('.footable-footer .items-number').addClass('selected');
        } else {
            $('.btns .toggle-button').addClass('disabled');
            $('.select-all-toggler').removeClass('selected');
            $('.footable-footer .items-number').removeClass('selected');
        }
        $('.footable-footer .items-number .items-selected span').html(totalSelected);
    };

    $scope.selectedIndex = 0;

    $rootScope.$on('tagEdited',function(event,ata){
        self.getUserConnections($scope.pageend, $scope.current, $scope.pagestart == 1 ? 0 : $scope.pagestart);
    })

    $scope.$on('full-refresh-complete', function (event, data) {


        $scope.check = false;
        $scope.connections = data.data;
        $scope.total = data.total;
        $scope.pagestart = parseInt(data.skip) == 0 ? 1 : parseInt(data.skip);
        $scope.pageend = parseInt(data.limit) > $scope.total ? $scope.total : parseInt(data.limit);
        $scope.current = 1;
        self.getTags();
        $scope.refresh_btn = 'Refresh';
        $scope.current = 0;
        $scope.max = 0;
    });
    
    $scope.startProgress = function (current) {
        $scope.refresh_btn = '';
        $scope.check = true;
        $scope.current = current;
        $scope.max = 100;
        $scope.offset = 0;
        $scope.timerCurrent = 10;
        $scope.uploadCurrent = 10;
        $scope.stroke = 15;
        $scope.radius = 125;
        $scope.isSemi = false;
        $scope.rounded = false;
        $scope.responsive = false;
        $scope.clockwise = true;
        $scope.currentColor = '#1fcefd';
        $scope.bgColor = 'transparent';
        $scope.duration = 7000;
        $scope.currentAnimation = 'easeOutCubic';
        $scope.animationDelay = 0;
        $scope.animations = [];

        angular.forEach(roundProgressService.animations, function (value, key) {
            $scope.animations.push(key);
        });

        $scope.getStyle = function () {
            var transform = ($scope.isSemi ? '' : 'translateY(-50%) ') + 'translateX(-50%)';

            return {
                'top': $scope.isSemi ? 'auto' : '50%',
                'padding': '40px 25px 58px',
                'border-radius': '50px',
                'width': '100px',
                'height': '100px',
                'transform': transform,
                '-moz-transform': transform,
                '-webkit-transform': transform,
                'font-size': '16'
            };
        };

        $scope.getColor = function () {
            return $scope.gradient ? 'url(#gradient)' : $scope.currentColor;
        };

        $scope.showPreciseCurrent = function (amount) {
            $timeout(function () {
                if (amount <= 0) {
                    $scope.preciseCurrent = $scope.current;
                } else if (amount >= $scope.current) {
                }
                else {
                    var math = $window.Math;
                    $scope.preciseCurrent = math.min(math.round(amount), $scope.max);
                }
            });
        };


    }

    $rootScope.$watch('counterConnectsProgress', function (newVal, oldVal) {
        if (newVal != oldVal && newVal > 50) {
            $scope.current = ($rootScope.counterConnectsProgress / $scope.total) > 1 ? 100 : ($rootScope.counterConnectsProgress * 100 / $scope.total);
            return;
        }
    });

    $scope.refresh = function () {
        if (!$scope.check) {
            $timeout(function () {
                $scope.current = (500 / $scope.total) > 1 ? 100 : (500 * 100 / $scope.total);
                $scope.startProgress($scope.current);
                connectionsService.refreshConnections();
            }, 0)
        }
    };

    $scope.searchByKeyword = function(){
        if(self.timeoutCancel){
            $timeout.cancel(self.timeoutCancel);
            delete self.timeoutCancel
        }
        self.timeoutCancel = $timeout(
            function(){
                self.getUserConnections(100, 1, 0)
            }, 700
        )
    };

    $scope.resizeTagInput = function(){
        var $input = $('.filter-tag input'),
            $helper = $('.tag-input-helper'),
            maxWidth = $('.filter-tag').width()-10,
            val = $input.val(),
            escaped = val.replace(/&/g, '&amp;').replace(/\s/g,' ').replace(/</g, '&lt;').replace(/>/g, '&gt;'),
            newWidth = 0;

        $helper.html(escaped);

        newWidth = $helper.width()+20;

        newWidth = (newWidth<=maxWidth)?newWidth:maxWidth;

        $input.width(newWidth);
    };

    $scope.$watch('selectedTags.tags', function(oldValue, newValue){
        if(oldValue.length || newValue.length){
            self.getUserConnections(100, 1, 0);
        }
    });

    self.getUserConnections(100, 1, 0);

}
angular
    .module('homer')
    .controller('connectionsTableCtrl', connectionsTableCtrl);
