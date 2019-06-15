xtools.globalcontribs = {};

$(function () {
    // Don't do anything if this isn't a Edit Counter page.
    if ($('body.globalcontribs').length === 0) {
        return;
    }

    xtools.application.setupContributionsNavListeners(function (params) {
        return `globalcontribs/${params.username}/${params.namespace}/${params.start}/${params.end}`;
    }, 'globalcontribs', xtools.globalcontribs.pageSize);
});
