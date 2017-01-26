"use strict";

var app = angular.module('ng-laravel',['xeditable','ui.bootstrap','ngStorage']);

app.service('setParam', function () {
    return {};
});

app.controller('findCategoryCtrl',function($scope,$stateParams,$window,$rootScope,$location,$auth,$localStorage,Restangular,SweetAlert,$http,setParam){
   
       
$scope.profile = $auth.getProfile().$$state.value;


    if(!$localStorage.key){
      getCurrentlocation();
    }else{
     $scope.current_location = $localStorage.key; 
     }

     $http({
            method: 'GET',
            url: 'laravel-backend/public/api/allSetCategory',
        }).then(function successCallback(response) {
           $scope.categoryImg = response.data;
        }, function errorCallback(response) {
        });
        
         
    $scope.getGroupByInterest = function(user){
        if(user ==undefined)
          user ={};

        if(user.interest =="")
          user.interest =null;
        
        if($localStorage.lng){
        user.longitude = $localStorage.lng;
        user.latitude = $localStorage.lat;
        }
        Restangular.all('groups/getGroupByInterest').customPOST(user).then(function(data) {
        
        $scope.findgroups = data.data;
        $scope.pagination = $scope.findgroups.metadata;

        }, function(response) {
            $scope.findgroups =response.data;
        $scope.pagination = $scope.findgroups.metadata;
        });
    };


    function getCurrentlocation (){
        if (navigator.geolocation) {
             navigator.geolocation.getCurrentPosition(function(position){

                    makeGlobalLocation (position.coords.latitude, position.coords.longitude);
                 });

        }

      }

        function makeGlobalLocation (lat ,lng){

          var latlng = new google.maps.LatLng(lat ,lng);
         // console.log(latlng);
              var addr = {};
                  var geocoder = new google.maps.Geocoder();
                      geocoder.geocode({ 'latLng': latlng }, function (results, status) {
                          if (status == google.maps.GeocoderStatus.OK) {
                              if (results[1]) {

                              for (var ii = 0; ii < results[0].address_components.length; ii++) {
                                  var types = results[0].address_components[ii].types.join(",");
                                  if (types == "sublocality,political" || types == "locality,political" || types == "neighborhood,political" || types == "administrative_area_level_3,political") {
                                      addr.city = results[0].address_components[ii].long_name;
                                  }else if(types == "administrative_area_level_2,political"){
                                      addr.city = results[0].address_components[ii].long_name;

                                  }else if(types == "country" || types == "country,political"){
                                      addr.city = results[0].address_components[ii].long_name;
                                  }

                                  if (types == "postal_code" || types == "postal_code_prefix,postal_code") {
                                      addr.postalcode = results[0].address_components[ii].long_name;
                                  }
                              }
                              $scope.$apply(function() {
                                      //console.log(addr['city']+', '+addr['postalcode']);
                                      $scope.current_location=addr['city']+', '+addr['postalcode'];
                                      $localStorage.key=addr['city']+', '+addr['postalcode'];
                                      $localStorage.lat=lat;
                                      $localStorage.lng=lng;

                              });
                              } else {
                                  console.log('Location not found');
                              }
                          }
                      });

        } 


        $rootScope.findSetCat = function(category){
       //console.log(category);
       
        $scope.setparam=setParam;
        
        $scope.setparam.tempval=category;
        $window.location.href = '/webplanex/#/home/find-group';
     };   
});




