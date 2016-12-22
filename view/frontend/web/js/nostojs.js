/**
 * Created by hannupolonen on 21/12/16.
 */
define([], function(){
    if (typeof nostojs == 'function') {
        return nostojs;
    } else if (typeof window['nostojs'] == 'function') {
        return window['nostojs'];
    }
});