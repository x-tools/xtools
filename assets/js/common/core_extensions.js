/**
 * Core JavaScript extensions
 * Adapted from https://github.com/MusikAnimal/pageviews
 */

String.prototype.descore = function () {
    return this.replace(/_/g, ' ');
};
String.prototype.score = function () {
    return this.replace(/ /g, '_');
};
String.prototype.escape = function () {
    var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;'
    };

    return this.replace(/[&<>"'\/]/g, function (s) {
        return entityMap[s];
    });
};

// remove duplicate values from Array
Array.prototype.unique = function () {
    return this.filter(function (value, index, array) {
        return array.indexOf(value) === index;
    });
};
