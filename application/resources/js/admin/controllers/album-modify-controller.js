angular.module('app').controller('AlbumModifyController', function($scope, $rootScope, $state, $stateParams, $http, modal, utils) {
    $scope.selectedTracks = [];
    $scope.selectedItem = {};
    $scope.errors = [];
    $scope.album = {};

    if ($rootScope.previousState.name === 'admin.editArtist') {
        $scope.album.artist = { name: $rootScope.previousState.params.name };
    }

    //fetch album
    if ($stateParams.id) {
        utils.showLoader(true);

        $http.post('get-album', {id:$stateParams.id}).success(function(data) {
            $scope.album = data;
        }).finally(function() {
            utils.hideLoader();
        });
    }

    $scope.uploadImage = function(image, userParams) {
        utils.uploadFileAndData('album/'+$scope.album.id+'/upload-image', image, userParams)
            .success(function(data) {
                $scope.album.image = data.image+'?'+utils.randomString(5);
            })
            .error(function(data) {
                utils.notify(data, 'error');
            });
    };

    $scope.uploadTrack = function(file, params, formFile) {
        $scope.trackFile = file;

        $scope.$apply(function() {
            $scope.selectedItem.url = formFile.name;
        })
    };

    $scope.updateAlbum = function(album) {
        if (utils.isDemo) {
            setTimeout(function() {
                alertify.delay(2000).error(utils.trans('noDemoPermissions'));
            }, 300);
        } else {
            if ($scope.album.id) {
                var promise = $http.put('album/'+album.id, album);
            } else {
                var promise = $http.post('album', album);
            }

            promise.success(function(data) {
                if (album.id) {
                    utils.notify(utils.trans('updatedAlbum'));
                } else {
                    $state.go('admin.editAlbum', {id:data.id});
                    utils.notify(utils.trans('createdAlbum'));
                }
            }).error(function(data) {
                alertify.delay(2000).error(data);
            });
        }
    };

    $scope.showUpdateTrackModal = function(track) {
        $scope.selectedItem = track;
        modal.show('update-track', $scope);
    };

    $scope.showNewTrackModal = function() {
        $scope.creatingTrack = true;

        $scope.selectedItem = {
            'album_name': $scope.album.name,
            number: $scope.album.tracks.length+1,
            artists: $scope.album.artist.name,
            duration: 300000,
            album_id: $scope.album.id,
            'spotify_popularity': 50
        };

        modal.show('update-track', $scope);
    };

    $scope.isTrackSelected = function(track) {
        return $scope.selectedTracks.indexOf(track) > -1;
    };

    $scope.selectTrack = function(track) {
        var idx = $scope.selectedTracks.indexOf(track);
        if (idx > -1) $scope.selectedTracks.splice(idx, 1);
        else $scope.selectedTracks.push(track);
    };

    $scope.toggleAllTracks = function() {
        if ($scope.selectedTracks.length === $scope.album.tracks.length) {
            $scope.selectedTracks = [];
        }
        else {
            $scope.selectedTracks = $scope.album.tracks.slice();
        }
    };

    $scope.updateOrCreateTrack = function() {
        if (utils.isDemo) {
            return alertify.delay(2000).error(utils.trans('noDemoPermissions'));
        }

        if ($scope.creatingTrack) {
            var promise = utils.uploadFileAndData('track', $scope.trackFile, $scope.selectedItem, 'post');
        } else {
            var promise = utils.uploadFileAndData('track/'+$scope.selectedItem.id, $scope.trackFile, $scope.selectedItem, 'put');
        }

        promise.success(function(data) {
            if ($scope.creatingTrack) {
                alertify.delay(2000).success(utils.trans('createdTrack'));
                $scope.album.tracks.push(data);
            } else {
                alertify.delay(2000).success(utils.trans('updatedTrack'));
            }

            $scope.trackFile = false;
            $scope.closeModal();
        }).error(function(data) {
            $scope.setErrors(data);
        })
    };

    $scope.deleteTracks = function() {
        $http.post('delete-tracks', { items: $scope.selectedTracks }).error(function(data) {
            utils.delay.error(data, 'error');
        }).success(function() {
            $scope.album.tracks = $scope.album.tracks.filter(function(track) {
               return $scope.selectedTracks.indexOf(track) === -1
            });

            $scope.selectedTracks = {};
        })
    };

    $scope.setErrors = function(data) {
        //if we've got back just a string show it in a toast
        if (angular.isString(data)) {
            return alertify.delay(2000).error(data);
        }

        //otherwise append each error to user modal
        for (var field in data) {
            $scope.errors.push(data[field][0]);
        }
    };

    $scope.closeModal = function() {
        modal.hide();
        $scope.creatingTrack = false;
        $scope.selectedItem  = {};
    }
});
