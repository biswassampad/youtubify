'use strict';

angular.module('app')

.controller('RegisterController', function($rootScope, $scope, $state, users, utils) {

    $scope.credentials = {};

    $scope.submit = function() {
        $scope.loading = true;

        return users.register($scope.credentials).success(function() {
            if (utils.getSetting('require_email_confirmation', false)) {
                utils.notify(utils.trans('confirmEmailMessage'), 'success', 6000);
            } else {
                $scope.credentials = {};
                $state.go('songs');
            }
        }).error(function(data) {
            $scope.errors = data;
        }).finally(function() {
            $scope.loading = false;
        })
    };

});



