angular.module('app').controller('ArtistAboutController', function($rootScope, $scope, $http, utils) {
    $scope.$watch('tabs.active', function(newTab, oldTab) {
        if (newTab === 'about' && ! $scope.initiated) {
            getBio();
        }
    });

    function getBio() {
        if ($scope.artist.bio) {
            var bio = JSON.parse($scope.artist.bio);
            $scope.bio = bio.bio;
            $scope.images = bio.images;
        } else {
            $scope.aboutLoading = true;

            $http.post('artist/'+$scope.artist.id+'/get-bio', {name: $scope.artist.name}).success(function(data) {
                $scope.bio = data.bio;
                $scope.images = data.images;
                $scope.initiated = true;
                $scope.aboutLoading = false;
            });
        }
    }
});


