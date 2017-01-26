(function () {

    'use strict';

    angular.module('accessnowapp')
    .controller('login-controller', ['$scope', 'loginService', 'growl', '$location', 'storageService', 'api',
        function ($scope, loginService, growl, $location, storageService, api) {

            $scope.LoginModel = {
                Email: "",
                Password: "",
            };

            $scope.CurrentDomain = storageService.getCurrentDomainToBuy();

            $scope.SignUpModel = {
                FirstName: "",
                LastName: "",
                Email: "",
                Password: "",
                RecieveNewsLetter: true,
                TermsAndConditions: false,
                IsPrivateUser: true,
                Country: "US"
            };

            $scope.AccountHelper = {

                BindCountries: function () {
                    api.getCountries()
                       .then(function (response) {
                           $scope.Countries = response;
                       });
                },

                Login: function () {

                    loginService.login({
                        emailaddress: $scope.LoginModel.Email,
                        password: $scope.LoginModel.Password
                    })
                    .then(function (serverResponse) {

                        if (serverResponse.status) {
                            loginService.setCredentials(serverResponse);

                            if (typeof $scope.CurrentDomain === 'undefined' || $scope.CurrentDomain == null) {
                                $location.path("/");
                            }
                            else {
                                $location.path("checkout"); //Redirect to home page need TODO: It would be user dashboard.
                            }
                        }
                        else {
                            growl.error("Invalid Username/Password");
                        }

                    }, function (error) {
                        growl.error("Something went wrong, please try again later.");
                    });
                },

                SignUp: function () {

                    loginService.signUp({
                        firstname: $scope.SignUpModel.FirstName,
                        lastname: $scope.SignUpModel.LastName,
                        emailaddress: $scope.SignUpModel.Email,
                        password: $scope.SignUpModel.Password,
                        recievenewsletter: $scope.SignUpModel.RecieveNewsLetter,
                        privateuser: $scope.SignUpModel.IsPrivateUser
                    })
                    .then(function (serverResponse) {

                        if (serverResponse.status) {
                            loginService.setCredentials(serverResponse);

                            if (typeof $scope.CurrentDomain === 'undefined' || $scope.CurrentDomain == null) {
                                $location.path("/");
                            }
                            else {
                                $location.path("checkout");
                            }
                        }
                        else {
                            growl.error(serverResponse.error);
                        }

                    }, function (error) {
                        growl.error("Something went wrong, please try again later.");
                    });
                }
            };



        }]);
})();
