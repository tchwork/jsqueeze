(function(window, coco) {

    function Test () {
        this._coco = coco;
    }

    Test.prototype = {
        get coco() {
            return this._coco;
        },
        set coco(newValue) {
            this._coco = newValue;
        }
    }

    window.Test = Test;

})(window, 'coco');

var test = new window.Test();

test.coco();
