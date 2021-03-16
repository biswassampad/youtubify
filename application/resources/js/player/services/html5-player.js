angular.module('app')

.factory('html5Player', function($rootScope, localStorage, utils) {

    var html5 = {

        /**
         * User facing player interface.
         */
        frontPlayer: false,

        /**
         * Youtube player implementation.
         */
        html5Player: false,

        bootstrapped: false,

        play: function() {
            this.html5Player.play();
        },

        pause: function() {
            this.html5Player.pause();
        },

        seekTo: function(time) {
            this.html5Player.currentTime = time;
        },

        loadVideo: function(track, autoPlay, quality) {
            this.html5Player.src = track.url;

            if (autoPlay) {
                this.play();
            }
        },

        cueVideo: function(track, autoPlay, quality) {
            this.html5Player.src = track.url;

            if (autoPlay) {
                this.play();
            }
        },

        getDuration: function() {
            return this.html5Player.seekable.end(0);
        },

        getCurrentTime: function() {
            return this.html5Player.currentTime;
        },

        getVolume: function() {
            return this.html5Player.volume / 1000;
        },

        setVolume: function(number) {
            this.html5Player.volume = number / 100;
        },

        mute: function() {
            this.html5Player.muted = true;
        },

        unMute: function() {
            this.html5Player.muted = false;
        },

        isPlaying: function() {
            return this.html5Player.currentTime > 0 && ! this.html5Player.paused && ! this.html5Player.ended;
        },

        init: function(frontPlayer) {
            if (this.bootstrapped) return;

            this.frontPlayer = frontPlayer;
            this.bootstrapped = true;

            this.html5Player    = document.createElement('video');
            this.html5Player.id = 'html5-player';

            angular.element(document.getElementsByClassName('video-inner-container')[0]).append(this.html5Player);

            this.frontPlayer.setVolume(localStorage.get('youtubify-volume', 17));

            html5.frontPlayer.loadLastPlayerTrack();

            setTimeout(function() {
                $rootScope.$emit('player.loaded');
            });

            angular.element(this.html5Player).on('ended', function() {
                $rootScope.$apply(function() {
                    html5.frontPlayer.playNext();
                })
            }).on('loadedmetadata', function() {
                $rootScope.$apply(function() {
                    html5.frontPlayer.loadingTrack = false;
                });
            }).on('playing', function() {
                setTimeout(function() {
                    $rootScope.$emit('player.trackChanged');
                });
            }).on('error', function() {
                var wasPlaying = html5.frontPlayer.isPlaying;

                utils.notify(utils.trans('couldntFindTrack'), 'error');
                html5.frontPlayer.playNext();

                html5.frontPlayer.loadingTrack = false;

                if ( ! wasPlaying) {
                    $rootScope.$apply(function() {
                        html5.frontPlayer.stop();
                    })
                }
            })
        }
    };

    return html5;
});
