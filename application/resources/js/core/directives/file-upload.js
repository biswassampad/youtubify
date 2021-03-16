angular.module('app').directive('fileUpload', function ($http, $parse, utils) {
    return {
        restrict: 'A',
        link: function ($scope, el, attrs) {
            el.on('click', function() {
                if (el.hasClass('disabled')) return;

                var input = angular.element('<input type="file" id="file-upload-input" style="visibility: hidden;" />');
                document.body.appendChild(input[0]);

                input.on('change', function() {
                    var fd = new FormData();
                    fd.append('file', this.files[0]);

                    var func = $parse(attrs.fileUpload)($scope);
                    func(fd, $parse(attrs.params)(), this.files[0]);
                    utils.removeNode('#file-upload-input');
                });

                input[0].click();
            })
        }
    };
});
