angular.module('app').controller('ArtistModifyController', function($scope, $rootScope, $state, $stateParams, $http, modal, utils) {
    $scope.artist = {};

    //fetch artist
    if ($stateParams.name) {
        utils.showLoader(true);

        $http.post('get-artist', {name:utils.decodeUrlParam($stateParams.name), force: true}).success(function(data) {
            $scope.artist = data;

            if ($scope.artist.genres && $scope.artist.genres.length) {
                var genres = '';

                angular.forEach($scope.artist.genres, function(genre) {
                    genres += genre.name+', ';
                });

                genres = genres.replace(/,\s*$/, '');
                $scope.genres = genres;
            }

            if ($scope.artist.bio) {
                try {
                    var bio = JSON.parse($scope.artist.bio);
                } catch(e) {
                    var bio = {};
                }

                $scope.bio = bio.bio;

                if (bio.images) {
                    $scope.images = bio.images.map(function(image) {
                        return image.url;
                    }).join("\n");
                }
            }

        }).finally(function() {
            utils.hideLoader();
        });
    }

    $scope.uploadImage = function(image, userParams) {
        if ( ! $scope.artist.id) return;

        $http.post('artist/'+$scope.artist.id+'/upload-image', image, {
                withCredentials: false,
                headers: { 'Content-Type': undefined },
                transformRequest: angular.identity,
                params: userParams
            })
            .success(function(data) {
                $scope.artist[userParams.type] = data[userParams.type]+'?'+utils.randomString(5);
            })
            .error(function(data) {
                alertify.delay(2000).error(data);
            });
    };

    $scope.updateArtist = function(artist) {
        if (utils.isDemo) {
            setTimeout(function() {
                alertify.delay(2000).error(utils.trans('noDemoPermissions'));
            }, 300);
        } else {
            var genres = $scope.genres;

            if (genres) {
                genres = genres.split(',').map(function(g) { return g.trim(); });
            }

            artist.genres = genres;

            if ( ! $scope.bio && ! $scope.images) {
                artist.bio = '';
            } else {
                var bio    = $scope.bio,
                    images = $scope.images.split("\n").map(function(url) {
                        return { url: url };
                    });

                artist.bio = JSON.stringify({ bio: bio, images: images });
            }

            if (artist.id) {
                var promise = $http.put('artist/'+artist.id, artist);
            } else {
                var promise = $http.post('artist', artist);
            }

            promise.success(function() {
                if ( ! artist.id) {
                    $state.go('admin.editArtist', {name:utils.encodeUrlParam(artist.name)});
                    utils.notify(utils.trans('createdArtist'));
                } else {
                    utils.notify(utils.trans('updatedArtist'));
                }
            }).error(function(data) {
                utils.notify(data, 'error');
            });
        }
    };

    $scope.deleteAlbum = function(album) {
        alertify.okBtn(utils.trans('delete'));
        alertify.cancelBtn(utils.trans('cancel'));

        alertify.confirm(utils.trans('confirmAlbumDelete'), function () {
            if (utils.isDemo) {
                return alertify.delay(2000).error(utils.trans('noDemoPermissions'));
            }

            $http.post('delete-albums', { items: [album] }).error(function(data) {
                alertify.delay(2000).error(data);
            }).success(function(data) {
                alertify.delay(2000).success(data);
                $scope.artist.albums.splice($scope.artist.albums.indexOf(album), 1);
            });
        }, function() {
            //
        });
    }
});
