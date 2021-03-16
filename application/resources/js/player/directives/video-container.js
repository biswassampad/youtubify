angular.module('app').directive('videoContainer', function(player, utils) {
	return {
		restrict: 'A',
		link: function ($scope, el) {
			el.on('click', function(e) {
				if (shouldCloseVideo(e)) {
					$scope.$apply(function() {
						return player.toggleVideo();
					})
				}

                if (shouldToggleFullScreen(e) && utils.getSetting('show_fullscreen_button')) {
                    $scope.$apply(function() {
                        return player.goFullScreen();
                    })
                }

                if (shouldDisableFullScreen(e)) {
                    return player.disableFullScreen();
                }
			});
		}
	};

	function shouldCloseVideo(e) {
		return e.target.classList.contains('backdrop') ||
			(e.target.classList.contains('close-lyrics-icon')) || e.target.parentNode.classList.contains('close-lyrics-icon');
	}

    function shouldToggleFullScreen(e) {
        return (e.target.classList.contains('toggle-fullscreen')) || e.target.parentNode.classList.contains('toggle-fullscreen');
    }

    function shouldDisableFullScreen(e) {
        return e.target.classList.contains('modal-inner-container');
    }
});