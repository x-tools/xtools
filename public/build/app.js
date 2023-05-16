(self["webpackChunkxtools"] = self["webpackChunkxtools"] || []).push([["app"],{

/***/ "./assets/js/adminstats.js":
/*!*********************************!*\
  !*** ./assets/js/adminstats.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
xtools.adminstats = {};
$(function () {
  var $projectInput = $('#project_input'),
    lastProject = $projectInput.val();

  // Don't do anything if this isn't an Admin Stats page.
  if ($('body.adminstats, body.patrollerstats, body.stewardstats').length === 0) {
    return;
  }
  xtools.application.setupMultiSelectListeners();
  $('.group-selector').on('change', function () {
    $('.action-selector').addClass('hidden');
    $('.action-selector--' + $(this).val()).removeClass('hidden');

    // Update title of form.
    $('.xt-page-title--title').text($.i18n('tool-' + $(this).val() + 'stats'));
    $('.xt-page-title--desc').text($.i18n('tool-' + $(this).val() + 'stats-desc'));
    var title = $.i18n('tool-' + $(this).val() + 'stats') + ' - ' + $.i18n('xtools-title');
    document.title = title;
    history.replaceState({}, title, '/' + $(this).val() + 'stats');

    // Change project to Meta if it's Steward Stats.
    if ('steward' === $(this).val()) {
      lastProject = $projectInput.val();
      $projectInput.val('meta.wikimedia.org');
    } else {
      $projectInput.val(lastProject);
    }
    xtools.application.setupMultiSelectListeners();
  });
});

/***/ }),

/***/ "./assets/js/articleinfo.js":
/*!**********************************!*\
  !*** ./assets/js/articleinfo.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.number.constructor.js */ "./node_modules/core-js/modules/es.number.constructor.js");
xtools.articleinfo = {};
$(function () {
  if (!$('body.articleinfo').length) {
    return;
  }
  var setupToggleTable = function setupToggleTable() {
    xtools.application.setupToggleTable(window.textshares, window.textsharesChart, 'percentage', $.noop);
  };
  var $textsharesContainer = $('.textshares-container');
  if ($textsharesContainer[0]) {
    /** global: xtBaseUrl */
    var url = xtBaseUrl + 'authorship/' + $textsharesContainer.data('project') + '/' + $textsharesContainer.data('article') + '/' + ($textsharesContainer.data('end-date') ? $textsharesContainer.data('end-date') + '/' : '');
    // Remove extraneous forward slash that would cause a 301 redirect, and request over HTTP instead of HTTPS.
    url = "".concat(url.replace(/\/$/, ''), "?htmlonly=yes");
    $.ajax({
      url: url,
      timeout: 30000
    }).done(function (data) {
      $textsharesContainer.replaceWith(data);
      xtools.application.buildSectionOffsets();
      xtools.application.setupTocListeners();
      xtools.application.setupColumnSorting();
      setupToggleTable();
    }).fail(function (_xhr, _status, message) {
      $textsharesContainer.replaceWith($.i18n('api-error', 'Authorship API: <code>' + message + '</code>'));
    });
  } else if ($('.textshares-table').length) {
    setupToggleTable();
  }

  // Setup the charts.
  var $chart = $('#year_count'),
    datasets = $chart.data('datasets');
  new Chart($chart, {
    type: 'bar',
    data: {
      labels: $chart.data('year-labels'),
      datasets: datasets
    },
    options: {
      responsive: true,
      legend: {
        display: false
      },
      tooltips: {
        mode: 'label',
        callbacks: {
          label: function label(tooltipItem) {
            return datasets[tooltipItem.datasetIndex].label + ': ' + Number(tooltipItem.yLabel).toLocaleString(i18nLang);
          }
        }
      },
      barValueSpacing: 20,
      scales: {
        yAxes: [{
          id: 'edits',
          type: 'linear',
          position: 'left',
          scaleLabel: {
            display: true,
            labelString: $.i18n('edits').capitalize()
          },
          ticks: {
            beginAtZero: true,
            callback: function callback(value) {
              if (Math.floor(value) === value) {
                return value.toLocaleString(i18nLang);
              }
            }
          },
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }, {
          id: 'size',
          type: 'linear',
          position: 'right',
          scaleLabel: {
            display: true,
            labelString: $.i18n('size').capitalize()
          },
          ticks: {
            beginAtZero: true,
            callback: function callback(value) {
              if (Math.floor(value) === value) {
                return value.toLocaleString(i18nLang);
              }
            }
          },
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }],
        xAxes: [{
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }]
      }
    }
  });
});

/***/ }),

/***/ "./assets/js/authorship.js":
/*!*********************************!*\
  !*** ./assets/js/authorship.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
$(function () {
  if (!$('body.authorship').length) {
    return;
  }

  // For the form page.
  var $showSelector = $('#show_selector');
  $showSelector.on('change', function (e) {
    $('.show-option').addClass('hidden').find('input').prop('disabled', true);
    $(".show-option--".concat(e.target.value)).removeClass('hidden').find('input').prop('disabled', false);
  });
  window.onload = function () {
    return $showSelector.trigger('change');
  };
  if ($('#authorship_chart').length) {
    setupChart();
  }
});
function setupChart() {
  var $chart = $('#authorship_chart'),
    percentages = Object.keys($chart.data('list')).slice(0, 10).map(function (author) {
      return $chart.data('list')[author].percentage;
    });

  // Add the "Others" slice if applicable.
  if ($chart.data('others')) {
    percentages.push($chart.data('others').percentage);
  }
  var authorshipChart = new Chart($chart, {
    type: 'pie',
    data: {
      labels: $chart.data('labels'),
      datasets: [{
        data: percentages,
        backgroundColor: $chart.data('colors'),
        borderColor: $chart.data('colors'),
        borderWidth: 1
      }]
    },
    options: {
      aspectRatio: 1,
      legend: {
        display: false
      },
      tooltips: {
        callbacks: {
          label: function label(tooltipItem, chartData) {
            var label = chartData.labels[tooltipItem.index],
              value = chartData.datasets[0].data[tooltipItem.index] / 100;
            return label + ': ' + value.toLocaleString(i18nLang, {
              style: 'percent',
              maximumFractionDigits: 1
            });
          }
        }
      }
    }
  });
}

/***/ }),

/***/ "./assets/js/autoedits.js":
/*!********************************!*\
  !*** ./assets/js/autoedits.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "./node_modules/core-js/modules/es.array.concat.js");
xtools.autoedits = {};
$(function () {
  if (!$('body.autoedits').length) {
    return;
  }
  var $contributionsContainer = $('.contributions-container'),
    $toolSelector = $('#tool_selector');

  // For the form page.
  if ($toolSelector.length) {
    xtools.autoedits.fetchTools = function (project) {
      $toolSelector.prop('disabled', true);
      $.get('/api/project/automated_tools/' + project).done(function (tools) {
        if (tools.error) {
          $toolSelector.prop('disabled', false);
          return; // Abort, project was invalid.
        }

        // These aren't tools, just metadata in the API response.
        delete tools.project;
        delete tools.elapsed_time;
        $toolSelector.html('<option value="none">' + $.i18n('none') + '</option>' + '<option value="all">' + $.i18n('all') + '</option>');
        Object.keys(tools).forEach(function (tool) {
          $toolSelector.append('<option value="' + tool + '">' + (tools[tool].label || tool) + '</option>');
        });
        $toolSelector.prop('disabled', false);
      });
    };
    $(document).ready(function () {
      $('#project_input').on('change.autoedits', function () {
        xtools.autoedits.fetchTools($('#project_input').val());
      });
    });
    xtools.autoedits.fetchTools($('#project_input').val());

    // All the other code below only applies to result pages.
    return;
  }

  // For result pages only...

  xtools.application.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
    var total = 0;
    Object.keys(newData).forEach(function (tool) {
      total += parseInt(newData[tool].count, 10);
    });
    var toolsCount = Object.keys(newData).length;
    /** global: i18nLang */
    $('.tools--tools').text(toolsCount.toLocaleString(i18nLang) + " " + $.i18n('num-tools', toolsCount));
    $('.tools--count').text(total.toLocaleString(i18nLang));
  });
  if ($contributionsContainer.length) {
    // Load the contributions browser, or set up the listeners if it is already present.
    var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
    xtools.application[initFunc](function (params) {
      return "".concat(params.target, "-contributions/").concat(params.project, "/").concat(params.username) + "/".concat(params.namespace, "/").concat(params.start, "/").concat(params.end);
    }, $contributionsContainer.data('target'));
  }
});

/***/ }),

/***/ "./assets/js/blame.js":
/*!****************************!*\
  !*** ./assets/js/blame.js ***!
  \****************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
xtools.blame = {};
$(function () {
  if (!$('body.blame').length) {
    return;
  }
  if ($('.diff-empty').length === $('.diff tr').length - 1) {
    $('.diff-empty').eq(0).text("(".concat($.i18n('diff-empty').toLowerCase(), ")")).addClass('text-muted text-center').prop('width', '20%');
  }
  $('.diff-addedline').each(function () {
    // Escape query to make regex-safe.
    var escapedQuery = xtools.blame.query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    var highlightMatch = function highlightMatch(selector) {
      var regex = new RegExp("(".concat(escapedQuery, ")"), 'gi');
      $(selector).html($(selector).html().replace(regex, "<strong>$1</strong>"));
    };
    if ($(this).find('.diffchange-inline').length) {
      $('.diffchange-inline').each(function () {
        highlightMatch(this);
      });
    } else {
      highlightMatch(this);
    }
  });

  // Handles the "Show" dropdown, show/hiding the associated input field accordingly.
  var $showSelector = $('#show_selector');
  $showSelector.on('change', function (e) {
    $('.show-option').addClass('hidden').find('input').prop('disabled', true);
    $(".show-option--".concat(e.target.value)).removeClass('hidden').find('input').prop('disabled', false);
  });
  window.onload = function () {
    return $showSelector.trigger('change');
  };
});

/***/ }),

/***/ "./assets/js/categoryedits.js":
/*!************************************!*\
  !*** ./assets/js/categoryedits.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.join.js */ "./node_modules/core-js/modules/es.array.join.js");
__webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
xtools.categoryedits = {};
$(function () {
  if (!$('body.categoryedits').length) {
    return;
  }
  $(document).ready(function () {
    xtools.categoryedits.$select2Input = $('#category_selector');
    setupCategoryInput();
    $('#project_input').on('xtools.projectLoaded', function (_e, data) {
      /** global: xtBaseUrl */
      $.get(xtBaseUrl + 'api/project/namespaces/' + data.project).done(function (data) {
        setupCategoryInput(data.api, data.namespaces[14]);
      });
    });
    $('form').on('submit', function () {
      $('#category_input').val(
      // Hidden input field
      xtools.categoryedits.$select2Input.val().join('|'));
    });
    xtools.application.setupToggleTable(window.countsByCategory, window.categoryChart, 'editCount', function (newData) {
      var totalEdits = 0,
        totalPages = 0;
      Object.keys(newData).forEach(function (category) {
        totalEdits += parseInt(newData[category].editCount, 10);
        totalPages += parseInt(newData[category].pageCount, 10);
      });
      var categoriesCount = Object.keys(newData).length;
      /** global: i18nLang */
      $('.category--category').text(categoriesCount.toLocaleString(i18nLang) + " " + $.i18n('num-categories', categoriesCount));
      $('.category--count').text(totalEdits.toLocaleString(i18nLang));
      $('.category--percent-of-edit-count').text((totalEdits / xtools.categoryedits.userEditCount).toLocaleString(i18nLang) * 100 + '%');
      $('.category--pages').text(totalPages.toLocaleString(i18nLang));
    });
    if ($('.contributions-container').length) {
      loadCategoryEdits();
    }
  });
});

/**
 * Load category edits HTML via AJAX, to not slow down the initial page load. Only load if container is present,
 * which is missing on index pages and in subroutes, e.g. categoryedits-contributions, etc.
 */
function loadCategoryEdits() {
  // Load the contributions browser, or set up the listeners if it is already present.
  var initFunc = $('.contributions-table').length ? 'setupContributionsNavListeners' : 'loadContributions';
  xtools.application[initFunc](function (params) {
    return 'categoryedits-contributions/' + params.project + '/' + params.username + '/' + params.categories + '/' + params.start + '/' + params.end;
  }, 'Category');
}

/**
 * Setups the Select2 control to search for pages in the Category namespace.
 * @param {String} [api] Fully qualified API endpoint.
 * @param {String} [ns] Name of the Category namespace.
 */
function setupCategoryInput(api, ns) {
  // First destroy any existing Select2 inputs.
  if (xtools.categoryedits.$select2Input.data('select2')) {
    xtools.categoryedits.$select2Input.off('change');
    xtools.categoryedits.$select2Input.select2('val', null);
    xtools.categoryedits.$select2Input.select2('data', null);
    xtools.categoryedits.$select2Input.select2('destroy');
  }
  var nsName = ns || xtools.categoryedits.$select2Input.data('ns');
  var params = {
    ajax: {
      url: api || xtools.categoryedits.$select2Input.data('api'),
      dataType: 'jsonp',
      jsonpCallback: 'categorySuggestionCallback',
      delay: 200,
      data: function data(search) {
        return {
          action: 'query',
          list: 'prefixsearch',
          format: 'json',
          pssearch: search.term || '',
          psnamespace: 14,
          cirrusUseCompletionSuggester: 'yes'
        };
      },
      processResults: function processResults(data) {
        var query = data ? data.query : {},
          results = [];
        if (query && query.prefixsearch.length) {
          results = query.prefixsearch.map(function (elem) {
            var title = elem.title.replace(new RegExp('^' + nsName + ':'), '');
            return {
              id: title.score(),
              text: title
            };
          });
        }
        return {
          results: results
        };
      }
    },
    placeholder: $.i18n('category-search'),
    maximumSelectionLength: 10,
    minimumInputLength: 1
  };
  xtools.categoryedits.$select2Input.select2(params);
}

/***/ }),

/***/ "./assets/js/common/application.js":
/*!*****************************************!*\
  !*** ./assets/js/common/application.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

__webpack_require__(/*! core-js/modules/es.array.iterator.js */ "./node_modules/core-js/modules/es.array.iterator.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.string.iterator.js */ "./node_modules/core-js/modules/es.string.iterator.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.iterator.js */ "./node_modules/core-js/modules/web.dom-collections.iterator.js");
__webpack_require__(/*! core-js/modules/web.url.js */ "./node_modules/core-js/modules/web.url.js");
__webpack_require__(/*! core-js/modules/web.url-search-params.js */ "./node_modules/core-js/modules/web.url-search-params.js");
__webpack_require__(/*! core-js/modules/es.object.assign.js */ "./node_modules/core-js/modules/es.object.assign.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.array.sort.js */ "./node_modules/core-js/modules/es.array.sort.js");
__webpack_require__(/*! core-js/modules/es.parse-float.js */ "./node_modules/core-js/modules/es.parse-float.js");
__webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
__webpack_require__(/*! core-js/modules/es.function.bind.js */ "./node_modules/core-js/modules/es.function.bind.js");
__webpack_require__(/*! core-js/modules/es.string.trim.js */ "./node_modules/core-js/modules/es.string.trim.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.index-of.js */ "./node_modules/core-js/modules/es.array.index-of.js");
__webpack_require__(/*! core-js/modules/es.array.filter.js */ "./node_modules/core-js/modules/es.array.filter.js");
__webpack_require__(/*! core-js/modules/es.date.now.js */ "./node_modules/core-js/modules/es.date.now.js");
__webpack_require__(/*! core-js/modules/es.date.to-string.js */ "./node_modules/core-js/modules/es.date.to-string.js");
__webpack_require__(/*! core-js/modules/web.timers.js */ "./node_modules/core-js/modules/web.timers.js");
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
xtools = {};
xtools.application = {};
xtools.application.vars = {
  sectionOffset: {}
};
xtools.application.chartGridColor = 'rgba(0, 0, 0, 0.1)';

// Make jQuery and xtools global (for now).
__webpack_require__.g.$ = __webpack_require__.g.jQuery = $;
__webpack_require__.g.xtools = xtools;
if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
  Chart.defaults.global.defaultFontColor = '#AAA';
  // Can't set a global default with our version of Chart.js, apparently,
  // so each chart initialization must explicitly set the grid line color.
  xtools.application.chartGridColor = '#333';
}

/** global: i18nLang */
/** global: i18nPaths */
$.i18n({
  locale: i18nLang
}).load(i18nPaths);
$(function () {
  // The $() around this code apparently isn't enough for Webpack, need another document-ready check.
  $(document).ready(function () {
    // TODO: move these listeners to a setup function and document how to use it.
    $('.xt-hide').on('click', function () {
      $(this).hide();
      $(this).siblings('.xt-show').show();
      if ($(this).parents('.panel-heading').length) {
        $(this).parents('.panel-heading').siblings('.panel-body').hide();
      } else {
        $(this).parents('.xt-show-hide--parent').next('.xt-show-hide--target').hide();
      }
    });
    $('.xt-show').on('click', function () {
      $(this).hide();
      $(this).siblings('.xt-hide').show();
      if ($(this).parents('.panel-heading').length) {
        $(this).parents('.panel-heading').siblings('.panel-body').show();
      } else {
        $(this).parents('.xt-show-hide--parent').next('.xt-show-hide--target').show();
      }
    });
    setupNavCollapsing();
    xtools.application.setupColumnSorting();
    setupTOC();
    setupStickyHeader();
    setupProjectListener();
    setupAutocompletion();
    displayWaitingNoticeOnSubmission();
    setupPieCharts();

    // Allow to add focus to input elements with i.e. ?focus=username
    if ('function' === typeof URL) {
      var focusElement = new URL(window.location.href).searchParams.get('focus');
      if (focusElement) {
        $("[name=".concat(focusElement, "]")).focus();
      }
    }
  });

  // Re-init forms, workaround for issues with Safari and Firefox.
  // See displayWaitingNoticeOnSubmission() for more.
  window.onpageshow = function (e) {
    if (e.persisted) {
      displayWaitingNoticeOnSubmission(true);
    }
  };
});

/**
 * Script to make interactive toggle table and pie chart.
 * For visual example, see the "Semi-automated edits" section of the AutoEdits tool.
 *
 * Example usage (see autoEdits/result.html.twig and js/autoedits.js for more):
 *     <table class="table table-bordered table-hover table-striped toggle-table">
 *         <thead>...</thead>
 *         <tbody>
 *             {% for tool, values in semi_automated %}
 *             <tr>
 *                 <!-- use the 'linked' class here because the cell contains a link -->
 *                 <td class="sort-entry--tool linked" data-value="{{ tool }}">
 *                     <span class="toggle-table--toggle" data-index="{{ loop.index0 }}" data-key="{{ tool }}">
 *                         <span class="glyphicon glyphicon-remove"></span>
 *                         <span class="color-icon" style="background:{{ chartColor(loop.index0) }}"></span>
 *                     </span>
 *                     {{ wiki.pageLink(...) }}
 *                 </td>
 *                 <td class="sort-entry--count" data-value="{{ values.count }}">
 *                     {{ values.count }}
 *                 </td>
 *             </tr>
 *             {% endfor %}
 *             ...
 *         </tbody>
 *     </table>
 *     <div class="toggle-table--chart">
 *         <canvas id="tool_chart" width="400" height="400"></canvas>
 *     </div>
 *     <script>
 *         window.toolsChart = new Chart($('#tool_chart'), { ... });
 *         window.countsByTool = {{ semi_automated | json_encode() | raw }};
 *         ...
 *
 *         // See autoedits.js for more
 *         xtools.application.setupToggleTable(window.countsByTool, window.toolsChart, 'count', function (newData) {
 *             // update the totals in toggle table based on newData
 *         });
 *     </script>
 *
 * @param  {Object}      dataSource  Object of data that makes up the chart
 * @param  {Chart}       chartObj    Reference to the pie chart associated with the .toggle-table
 * @param  {String|null} [valueKey]  The name of the key within entries of dataSource, where the value is
 *                                   what's shown in the chart. If omitted or null, `dataSource` is assumed
 *                                   to be of the structure: { 'a' => 123, 'b' => 456 }
 * @param  {Function} updateCallback Callback to update the .toggle-table totals. `toggleTableData`
 *                                   is passed in which contains the new data, you just need to
 *                                   format it (maybe need to use i18n, update multiple cells, etc.).
 *                                   The second parameter that is passed back is the 'key' of the toggled
 *                                   item, and the third is the index of the item.
 */
xtools.application.setupToggleTable = function (dataSource, chartObj, valueKey, updateCallback) {
  var toggleTableData;
  $('.toggle-table').on('click', '.toggle-table--toggle', function () {
    if (!toggleTableData) {
      // must be cloned
      toggleTableData = Object.assign({}, dataSource);
    }
    var index = $(this).data('index'),
      key = $(this).data('key');

    // must use .attr instead of .prop as sorting script will clone DOM elements
    if ($(this).attr('data-disabled') === 'true') {
      toggleTableData[key] = dataSource[key];
      chartObj.data.datasets[0].data[index] = parseInt(valueKey ? toggleTableData[key][valueKey] : toggleTableData[key], 10);
      $(this).attr('data-disabled', 'false');
    } else {
      delete toggleTableData[key];
      chartObj.data.datasets[0].data[index] = null;
      $(this).attr('data-disabled', 'true');
    }

    // gray out row in table
    $(this).parents('tr').toggleClass('excluded');

    // change the hover icon from a 'x' to a '+'
    $(this).find('.glyphicon').toggleClass('glyphicon-remove').toggleClass('glyphicon-plus');

    // update stats
    updateCallback(toggleTableData, key, index);
    chartObj.update();
  });
};

/**
 * If there are more tool links in the nav than will fit in the viewport, move the last entry to the More menu,
 * one at a time, until it all fits. This does not listen for window resize events.
 */
function setupNavCollapsing() {
  var windowWidth = $(window).width(),
    toolNavWidth = $('.tool-links').outerWidth(),
    navRightWidth = $('.nav-buttons').outerWidth();

  // Ignore if in mobile responsive view
  if (windowWidth < 768) {
    return;
  }

  // Do this first so we account for the space the More menu takes up
  if (toolNavWidth + navRightWidth > windowWidth) {
    $('.tool-links--more').removeClass('hidden');
  }

  // Don't loop more than there are links in the nav.
  // This more just a safeguard against an infinite loop should something go wrong.
  var numLinks = $('.tool-links--entry').length;
  while (numLinks > 0 && toolNavWidth + navRightWidth > windowWidth) {
    // Remove the last tool link that is not the current tool being used
    var $link = $('.tool-links--nav > .tool-links--entry:not(.active)').last().remove();
    $('.tool-links--more .dropdown-menu').append($link);
    toolNavWidth = $('.tool-links').outerWidth();
    numLinks--;
  }
}

/**
 * Sorting of columns.
 *
 *  Example usage:
 *   {% for key in ['username', 'edits', 'minor', 'date'] %}
 *      <th>
 *         <span class="sort-link sort-link--{{ key }}" data-column="{{ key }}">
 *            {{ msg(key) | capitalize }}
 *            <span class="glyphicon glyphicon-sort"></span>
 *         </span>
 *      </th>
 *  {% endfor %}
 *   <th class="sort-link" data-column="username">Username</th>
 *   ...
 *   <td class="sort-entry--username" data-value="{{ username }}">{{ username }}</td>
 *   ...
 *
 * Data type is automatically determined, with support for integer,
 *   floats, and strings, including date strings (e.g. "2016-01-01 12:59")
 */
xtools.application.setupColumnSorting = function () {
  var sortDirection, sortColumn;
  $('.sort-link').on('click', function () {
    sortDirection = sortColumn === $(this).data('column') ? -sortDirection : 1;
    $('.sort-link .glyphicon').removeClass('glyphicon-sort-by-alphabet-alt glyphicon-sort-by-alphabet').addClass('glyphicon-sort');
    var newSortClassName = sortDirection === 1 ? 'glyphicon-sort-by-alphabet-alt' : 'glyphicon-sort-by-alphabet';
    $(this).find('.glyphicon').addClass(newSortClassName).removeClass('glyphicon-sort');
    sortColumn = $(this).data('column');
    var $table = $(this).parents('table');
    var $entries = $table.find('.sort-entry--' + sortColumn).parent();
    if (!$entries.length) {
      return;
    }
    $entries.sort(function (a, b) {
      var before = $(a).find('.sort-entry--' + sortColumn).data('value') || 0,
        after = $(b).find('.sort-entry--' + sortColumn).data('value') || 0;

      // Cast numerical strings into floats for faster sorting.
      if (!isNaN(before)) {
        before = parseFloat(before) || 0;
      }
      if (!isNaN(after)) {
        after = parseFloat(after) || 0;
      }
      if (before < after) {
        return sortDirection;
      } else if (before > after) {
        return -sortDirection;
      } else {
        return 0;
      }
    });

    // Re-fill the rank column, if applicable.
    if ($('.sort-entry--rank').length > 0) {
      $.each($entries, function (index, entry) {
        $(entry).find('.sort-entry--rank').text(index + 1);
      });
    }
    $table.find('tbody').html($entries);
  });
};

/**
 * Floating table of contents.
 *
 * Example usage (see articleInfo/result.html.twig for more):
 *     <p class="text-center xt-heading-subtitle">
 *         ...
 *     </p>
 *     <div class="text-center xt-toc">
 *         {% set sections = ['generalstats', 'usertable', 'yearcounts', 'monthcounts'] %}
 *         {% for section in sections %}
 *             <span>
 *                 <a href="#{{ section }}" data-section="{{ section }}">{{ msg(section) }}</a>
 *             </span>
 *         {% endfor %}
 *     </div>
 *     ...
 *     {% set content %}
 *         ...content for general stats...
 *     {% endset %}
 *     {{ layout.content_block('generalstats', content) }}
 *     ...
 */
function setupTOC() {
  var $toc = $('.xt-toc');
  if (!$toc || !$toc[0]) {
    return;
  }
  xtools.application.vars.tocHeight = $toc.height();

  // listeners on the section links
  var setupTocListeners = function setupTocListeners() {
    $('.xt-toc').find('a').off('click').on('click', function (e) {
      document.activeElement.blur();
      var $newSection = $('#' + $(e.target).data('section'));
      $(window).scrollTop($newSection.offset().top - xtools.application.vars.tocHeight);
      $(this).parents('.xt-toc').find('a').removeClass('bold');
      createTocClone();
      xtools.application.vars.$tocClone.addClass('bold');
    });
  };
  xtools.application.setupTocListeners = setupTocListeners;

  // clone the TOC and add position:fixed
  var createTocClone = function createTocClone() {
    if (xtools.application.vars.$tocClone) {
      return;
    }
    xtools.application.vars.$tocClone = $toc.clone();
    xtools.application.vars.$tocClone.addClass('fixed');
    $toc.after(xtools.application.vars.$tocClone);
    setupTocListeners();
  };

  // build object containing offsets of each section
  xtools.application.buildSectionOffsets = function () {
    $.each($toc.find('a'), function (index, tocMember) {
      var id = $(tocMember).data('section');
      xtools.application.vars.sectionOffset[id] = $('#' + id).offset().top;
    });
  };

  // rebuild section offsets when sections are shown/hidden
  $('.xt-show, .xt-hide').on('click', xtools.application.buildSectionOffsets);
  xtools.application.buildSectionOffsets();
  setupTocListeners();
  var tocOffsetTop = $toc.offset().top;
  $(window).on('scroll.toc', function (e) {
    var windowOffset = $(e.target).scrollTop();
    var inRange = windowOffset > tocOffsetTop;
    if (inRange) {
      if (!xtools.application.vars.$tocClone) {
        createTocClone();
      }

      // Bolden the link for whichever section we're in
      var $activeMember;
      Object.keys(xtools.application.vars.sectionOffset).forEach(function (section) {
        if (windowOffset > xtools.application.vars.sectionOffset[section] - xtools.application.vars.tocHeight - 1) {
          $activeMember = xtools.application.vars.$tocClone.find('a[data-section="' + section + '"]');
        }
      });
      xtools.application.vars.$tocClone.find('a').removeClass('bold');
      if ($activeMember) {
        $activeMember.addClass('bold');
      }
    } else if (!inRange && xtools.application.vars.$tocClone) {
      // remove the clone once we're out of range
      xtools.application.vars.$tocClone.remove();
      xtools.application.vars.$tocClone = null;
    }
  });
}

/**
 * Make any tables with the class 'table-sticky-header' have sticky headers.
 * E.g. as you scroll the heading row will be fixed at the top for reference.
 */
function setupStickyHeader() {
  var $header = $('.table-sticky-header');
  if (!$header || !$header[0]) {
    return;
  }
  var $headerRow = $header.find('thead tr').eq(0),
    $headerClone;

  // Make a clone of the header to maintain placement of the original header,
  // making the original header the sticky one. This way event listeners on it
  // (such as column sorting) will still work.
  var cloneHeader = function cloneHeader() {
    if ($headerClone) {
      return;
    }
    $headerClone = $headerRow.clone();
    $headerRow.addClass('sticky-heading');
    $headerRow.before($headerClone);

    // Explicitly set widths of each column, which are lost with position:absolute.
    $headerRow.find('th').each(function (index) {
      $(this).css('width', $headerClone.find('th').eq(index).outerWidth());
    });
    $headerRow.css('width', $headerClone.outerWidth() + 1);
  };
  var headerOffsetTop = $header.offset().top;
  $(window).on('scroll.stickyHeader', function (e) {
    var windowOffset = $(e.target).scrollTop();
    var inRange = windowOffset > headerOffsetTop;
    if (inRange && !$headerClone) {
      cloneHeader();
    } else if (!inRange && $headerClone) {
      // Remove the clone once we're out of range,
      // and make the original un-sticky.
      $headerRow.removeClass('sticky-heading');
      $headerClone.remove();
      $headerClone = null;
    } else if ($headerClone) {
      // The header is position:absolute so it will follow with X scrolling,
      // but for Y we must go by the window scroll position.
      $headerRow.css('top', $(window).scrollTop() - $header.offset().top);
    }
  });
}

/**
 * Add listener to the project input field to update any namespace selectors and autocompletion fields.
 */
function setupProjectListener() {
  var $projectInput = $('#project_input');

  // Stop here if there is no project field
  if (!$projectInput) {
    return;
  }

  // If applicable, setup namespace selector with real time updates when changing projects.
  // This will also set `apiPath` so that autocompletion will query the right wiki.
  if ($projectInput.length && $('#namespace_select').length) {
    setupNamespaceSelector();
    // Otherwise, if there's a user or page input field, we still need to update `apiPath`
    // for the user input autocompletion when the project is changed.
  } else if ($('#user_input')[0] || $('#article_input')[0]) {
    // keep track of last valid project
    xtools.application.vars.lastProject = $projectInput.val();
    $projectInput.on('change', function () {
      var newProject = this.value;

      /** global: xtBaseUrl */
      $.get(xtBaseUrl + 'api/project/normalize/' + newProject).done(function (data) {
        // Keep track of project API path for use in page title autocompletion
        xtools.application.vars.apiPath = data.api;
        xtools.application.vars.lastProject = newProject;
        setupAutocompletion();

        // Other pages may listen for this custom event.
        $projectInput.trigger('xtools.projectLoaded', data);
      }).fail(revertToValidProject.bind(this, newProject));
    });
  }
}

/**
 * Use the wiki input field to populate the namespace selector.
 * This also updates `apiPath` and calls setupAutocompletion().
 */
function setupNamespaceSelector() {
  // keep track of last valid project
  xtools.application.vars.lastProject = $('#project_input').val();
  $('#project_input').off('change').on('change', function () {
    // Disable the namespace selector and show a spinner while the data loads.
    $('#namespace_select').prop('disabled', true);
    var newProject = this.value;

    /** global: xtBaseUrl */
    $.get(xtBaseUrl + 'api/project/namespaces/' + newProject).done(function (data) {
      // Clone the 'all' option (even if there isn't one),
      // and replace the current option list with this.
      var $allOption = $('#namespace_select option[value="all"]').eq(0).clone();
      $("#namespace_select").html($allOption);

      // Keep track of project API path for use in page title autocompletion.
      xtools.application.vars.apiPath = data.api;

      // Add all of the new namespace options.
      for (var ns in data.namespaces) {
        if (!data.namespaces.hasOwnProperty(ns)) {
          continue; // Skip keys from the prototype.
        }

        var nsName = parseInt(ns, 10) === 0 ? $.i18n('mainspace') : data.namespaces[ns];
        $('#namespace_select').append("<option value=" + ns + ">" + nsName + "</option>");
      }
      // Default to mainspace being selected.
      $("#namespace_select").val(0);
      xtools.application.vars.lastProject = newProject;

      // Re-init autocompletion
      setupAutocompletion();
    }).fail(revertToValidProject.bind(this, newProject)).always(function () {
      $('#namespace_select').prop('disabled', false);
    });
  });

  // If they change the namespace, update autocompletion,
  // which will ensure only pages in the selected namespace
  // show up in the autocompletion
  $('#namespace_select').on('change', setupAutocompletion);
}

/**
 * Called by setupNamespaceSelector or setupProjectListener when the user changes to a project that doesn't exist.
 * This throws a warning message and reverts back to the last valid project.
 * @param {string} newProject - project they attempted to add
 */
function revertToValidProject(newProject) {
  $('#project_input').val(xtools.application.vars.lastProject);
  $('.site-notice').append("<div class='alert alert-warning alert-dismissible' role='alert'>" + $.i18n('invalid-project', "<strong>" + newProject + "</strong>") + "<button class='close' data-dismiss='alert' aria-label='Close'>" + "<span aria-hidden='true'>&times;</span>" + "</button>" + "</div>");
}

/**
 * Setup autocompletion of pages if a page input field is present.
 */
function setupAutocompletion() {
  var $articleInput = $('#article_input'),
    $userInput = $('#user_input'),
    $namespaceInput = $("#namespace_select");

  // Make sure typeahead-compatible fields are present
  if (!$articleInput[0] && !$userInput[0] && !$('#project_input')[0]) {
    return;
  }

  // Destroy any existing instances
  if ($articleInput.data('typeahead')) {
    $articleInput.data('typeahead').destroy();
  }
  if ($userInput.data('typeahead')) {
    $userInput.data('typeahead').destroy();
  }

  // set initial value for the API url, which is put as a data attribute in forms.html.twig
  if (!xtools.application.vars.apiPath) {
    xtools.application.vars.apiPath = $('#article_input').data('api') || $('#user_input').data('api');
  }

  // Defaults for typeahead options. preDispatch and preProcess will be
  // set accordingly for each typeahead instance
  var typeaheadOpts = {
    url: xtools.application.vars.apiPath,
    timeout: 200,
    triggerLength: 1,
    method: 'get',
    preDispatch: null,
    preProcess: null
  };
  if ($articleInput[0]) {
    $articleInput.typeahead({
      ajax: Object.assign(typeaheadOpts, {
        preDispatch: function preDispatch(query) {
          // If there is a namespace selector, make sure we search
          // only within that namespace
          if ($namespaceInput[0] && $namespaceInput.val() !== '0') {
            var nsName = $namespaceInput.find('option:selected').text().trim();
            query = nsName + ':' + query;
          }
          return {
            action: 'query',
            list: 'prefixsearch',
            format: 'json',
            pssearch: query
          };
        },
        preProcess: function preProcess(data) {
          var nsName = '';
          // Strip out namespace name if applicable
          if ($namespaceInput[0] && $namespaceInput.val() !== '0') {
            nsName = $namespaceInput.find('option:selected').text().trim();
          }
          return data.query.prefixsearch.map(function (elem) {
            return elem.title.replace(new RegExp('^' + nsName + ':'), '');
          });
        }
      })
    });
  }
  if ($userInput[0]) {
    $userInput.typeahead({
      ajax: Object.assign(typeaheadOpts, {
        preDispatch: function preDispatch(query) {
          return {
            action: 'query',
            list: 'prefixsearch',
            format: 'json',
            pssearch: 'User:' + query
          };
        },
        preProcess: function preProcess(data) {
          var results = data.query.prefixsearch.map(function (elem) {
            return elem.title.split('/')[0].substr(elem.title.indexOf(':') + 1);
          });
          return results.filter(function (value, index, array) {
            return array.indexOf(value) === index;
          });
        }
      })
    });
  }
}

/**
 * For any form submission, this disables the submit button and replaces its text with
 * a loading message and a counting timer.
 * @param {boolean} [undo] Revert the form back to the initial state.
 *                         This is used on page load to solve an issue with Safari and Firefox
 *                         where after browsing back to the form, the "loading" state persists.
 */
function displayWaitingNoticeOnSubmission(undo) {
  if (undo) {
    // Re-enable form
    $('.form-control').prop('readonly', false);
    $('.form-submit').prop('disabled', false);
    $('.form-submit').text($.i18n('submit')).prop('disabled', false);
  } else {
    $('#content form').on('submit', function () {
      // Remove focus from any active element
      document.activeElement.blur();

      // Disable the form so they can't hit Enter to re-submit
      $('.form-control').prop('readonly', true);

      // Change the submit button text.
      $('.form-submit').prop('disabled', true).html($.i18n('loading') + " <span id='submit_timer'></span>");

      // Add the counter.
      var startTime = Date.now();
      setInterval(function () {
        var elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
        var minutes = Math.floor(elapsedSeconds / 60);
        var seconds = ('00' + (elapsedSeconds - minutes * 60)).slice(-2);
        $('#submit_timer').text(minutes + ":" + seconds);
      }, 1000);
    });
  }
}
function setupPieCharts() {
  var $charts = $('.xt-pie-chart');
}

/**
 * Handles the multi-select inputs on some index pages.
 */
xtools.application.setupMultiSelectListeners = function () {
  var $inputs = $('.multi-select--body:not(.hidden) .multi-select--option');
  $inputs.on('change', function () {
    // If all sections are selected, select the 'All' checkbox, and vice versa.
    $('.multi-select--all').prop('checked', $('.multi-select--body:not(.hidden) .multi-select--option:checked').length === $inputs.length);
  });
  // Uncheck/check all when the 'All' checkbox is modified.
  $('.multi-select--all').on('click', function () {
    $inputs.prop('checked', $(this).prop('checked'));
  });
};

/***/ }),

/***/ "./assets/js/common/contributions-lists.js":
/*!*************************************************!*\
  !*** ./assets/js/common/contributions-lists.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.object.assign.js */ "./node_modules/core-js/modules/es.object.assign.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.array.iterator.js */ "./node_modules/core-js/modules/es.array.iterator.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.string.iterator.js */ "./node_modules/core-js/modules/es.string.iterator.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.iterator.js */ "./node_modules/core-js/modules/web.dom-collections.iterator.js");
__webpack_require__(/*! core-js/modules/web.url-search-params.js */ "./node_modules/core-js/modules/web.url-search-params.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.search.js */ "./node_modules/core-js/modules/es.string.search.js");
__webpack_require__(/*! core-js/modules/es.date.to-string.js */ "./node_modules/core-js/modules/es.date.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
Object.assign(xtools.application.vars, {
  initialOffset: '',
  offset: '',
  prevOffsets: [],
  initialLoad: false
});

/**
 * Set the initial offset for contributions lists, based on what was
 * supplied in the contributions container.
 */
function setInitialOffset() {
  if (!xtools.application.vars.offset) {
    // The initialOffset should be what was given via the .contributions-container.
    // This is used to determine if we're back on the first page or not.
    xtools.application.vars.initialOffset = $('.contributions-container').data('offset');
    // The offset will from here represent which page we're on, and is compared with
    // intitialEditOffset to know if we're on the first page.
    xtools.application.vars.offset = xtools.application.vars.initialOffset;
  }
}

/**
 * Loads configured type of contributions from the server and lists them in the DOM.
 * The navigation aids and showing/hiding of loading text is also handled here.
 * @param {function} endpointFunc The callback that takes the params set on .contributions-container
 *     and returns a string that is the endpoint to fetch from (without the offset appended).
 * @param {String} apiTitle The name of the API (could be i18n key), used in error reporting.
 */
xtools.application.loadContributions = function (endpointFunc, apiTitle) {
  setInitialOffset();
  var $contributionsContainer = $('.contributions-container'),
    $contributionsLoading = $('.contributions-loading'),
    params = $contributionsContainer.data(),
    endpoint = endpointFunc(params),
    limit = parseInt(params.limit, 10) || 50,
    urlParams = new URLSearchParams(window.location.search),
    newUrl = xtBaseUrl + endpoint + '/' + xtools.application.vars.offset,
    oldToolPath = location.pathname.split('/')[1],
    newToolPath = newUrl.split('/')[1];

  // Gray out contributions list.
  $contributionsContainer.addClass('contributions-container--loading');

  // Show the 'Loading...' text. CSS will hide the "Previous" / "Next" links to prevent jumping.
  $contributionsLoading.show();
  urlParams.set('limit', limit.toString());
  urlParams.append('htmlonly', 'yes');

  /** global: xtBaseUrl */
  $.ajax({
    // Make sure to include any URL parameters, such as tool=Huggle (for AutoEdits).
    url: newUrl + '?' + urlParams.toString(),
    timeout: 60000
  }).always(function () {
    $contributionsContainer.removeClass('contributions-container--loading');
    $contributionsLoading.hide();
  }).done(function (data) {
    $contributionsContainer.html(data).show();
    xtools.application.setupContributionsNavListeners(endpointFunc, apiTitle);

    // Set an initial offset if we don't have one already so that we know when we're on the first page of contribs.
    if (!xtools.application.vars.initialOffset) {
      xtools.application.vars.initialOffset = $('.contribs-row-date').first().data('value');

      // In this case we know we are loading contribs for this first time via AJAX (such as at /autoedits),
      // hence we'll set the initialLoad flag to true, so we know not to unnecessarily pollute the URL
      // after we get back the data (see below).
      xtools.application.vars.initialLoad = true;
    }
    if (oldToolPath !== newToolPath) {
      // Happens when a subrequest is made to a different controller action.
      // For instance, /autoedits embeds /nonautoedits-contributions.
      var regexp = new RegExp("^/".concat(newToolPath, "/(.*)/"));
      newUrl = newUrl.replace(regexp, "/".concat(oldToolPath, "/$1/"));
    }

    // Do not run on the initial page load. This is to retain a clean URL:
    // (i.e. /autoedits/enwiki/Example, rather than /autoedits/enwiki/Example/0///2015-07-02T15:50:48?limit=50)
    // When user paginates (requests made NOT on the initial page load), we do want to update the URL.
    if (!xtools.application.vars.initialLoad) {
      // Update URL so we can have permalinks.
      // 'htmlonly' should be removed as it's an internal param.
      urlParams["delete"]('htmlonly');
      window.history.replaceState(null, document.title, newUrl + '?' + urlParams.toString());

      // Also scroll to the top of the contribs container.
      $contributionsContainer.parents('.panel')[0].scrollIntoView();
    } else {
      // So that pagination through the contribs will update the URL and scroll into view.
      xtools.application.vars.initialLoad = false;
    }
    if (xtools.application.vars.offset < xtools.application.vars.initialOffset) {
      $('.contributions--prev').show();
    } else {
      $('.contributions--prev').hide();
    }
    if ($('.contributions-table tbody tr').length < limit) {
      $('.next-edits').hide();
    }
  }).fail(function (_xhr, _status, message) {
    $contributionsLoading.hide();
    $contributionsContainer.html($.i18n('api-error', $.i18n(apiTitle) + ' API: <code>' + message + '</code>')).show();
  });
};

/**
 * Set up listeners for navigating contribution lists.
 */
xtools.application.setupContributionsNavListeners = function (endpointFunc, apiTitle) {
  setInitialOffset();

  // Previous arrow.
  $('.contributions--prev').off('click').one('click', function (e) {
    e.preventDefault();
    xtools.application.vars.offset = xtools.application.vars.prevOffsets.pop() || xtools.application.vars.initialOffset;
    xtools.application.loadContributions(endpointFunc, apiTitle);
  });

  // Next arrow.
  $('.contributions--next').off('click').one('click', function (e) {
    e.preventDefault();
    if (xtools.application.vars.offset) {
      xtools.application.vars.prevOffsets.push(xtools.application.vars.offset);
    }
    xtools.application.vars.offset = $('.contribs-row-date').last().data('value');
    xtools.application.loadContributions(endpointFunc, apiTitle);
  });

  // The 'Limit:' dropdown.
  $('#contributions_limit').on('change', function (e) {
    var limit = parseInt(e.target.value, 10);
    $('.contributions-container').data('limit', limit);
    $('.contributions--prev-text').text($.i18n('pager-newer-n', limit).capitalize());
    $('.contributions--next-text').text($.i18n('pager-older-n', limit).capitalize());
  });
};

/***/ }),

/***/ "./assets/js/common/core_extensions.js":
/*!*********************************************!*\
  !*** ./assets/js/common/core_extensions.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.array.filter.js */ "./node_modules/core-js/modules/es.array.filter.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.index-of.js */ "./node_modules/core-js/modules/es.array.index-of.js");
__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "./node_modules/core-js/modules/es.object.define-property.js");
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
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

/** https://stackoverflow.com/a/3291856/604142 (CC BY-SA 4.0) */
Object.defineProperty(String.prototype, 'capitalize', {
  value: function value() {
    return this.charAt(0).toUpperCase() + this.slice(1);
  },
  enumerable: false
});

/***/ }),

/***/ "./assets/js/editcounter.js":
/*!**********************************!*\
  !*** ./assets/js/editcounter.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.array.filter.js */ "./node_modules/core-js/modules/es.array.filter.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.date.to-string.js */ "./node_modules/core-js/modules/es.date.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.join.js */ "./node_modules/core-js/modules/es.array.join.js");
__webpack_require__(/*! core-js/modules/es.array.index-of.js */ "./node_modules/core-js/modules/es.array.index-of.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.array.reduce.js */ "./node_modules/core-js/modules/es.array.reduce.js");
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "./node_modules/core-js/modules/es.array.concat.js");
xtools.editcounter = {};

/**
 * Namespaces that have been excluded from view via namespace toggle table.
 * @type {Array}
 */
xtools.editcounter.excludedNamespaces = [];

/**
 * Chart labels for the month/yearcount charts.
 * @type {Object} Keys are the chart IDs, values are arrays of strings.
 */
xtools.editcounter.chartLabels = {};

/**
 * Number of digits of the max month/year total. We want to keep this consistent
 * for aesthetic reasons, even if the updated totals are fewer digits in size.
 * @type {Object} Keys are the chart IDs, values are integers.
 */
xtools.editcounter.maxDigits = {};
$(function () {
  // Don't do anything if this isn't a Edit Counter page.
  if ($('body.editcounter').length === 0) {
    return;
  }
  xtools.application.setupMultiSelectListeners();

  // Set up charts.
  $('.chart-wrapper').each(function () {
    var chartType = $(this).data('chart-type');
    if (chartType === undefined) {
      return false;
    }
    var data = $(this).data('chart-data');
    var labels = $(this).data('chart-labels');
    var $ctx = $('canvas', $(this));

    /** global: Chart */
    new Chart($ctx, {
      type: chartType,
      data: {
        labels: labels,
        datasets: [{
          data: data
        }]
      }
    });
    return undefined;
  });

  // Set up namespace toggle chart.
  xtools.application.setupToggleTable(window.namespaceTotals, window.namespaceChart, null, toggleNamespace);
});

/**
 * Callback for setupToggleTable(). This will show/hide a given namespace from
 * all charts, and update totals and percentages.
 * @param {Object} newData New namespaces and totals, as returned by setupToggleTable.
 * @param {String} key Namespace ID of the toggled namespace.
 */
function toggleNamespace(newData, key) {
  var total = 0,
    counts = [];
  Object.keys(newData).forEach(function (namespace) {
    var count = parseInt(newData[namespace], 10);
    counts.push(count);
    total += count;
  });
  var namespaceCount = Object.keys(newData).length;

  /** global: i18nLang */
  $('.namespaces--namespaces').text(namespaceCount.toLocaleString(i18nLang) + ' ' + $.i18n('num-namespaces', namespaceCount));
  $('.namespaces--count').text(total.toLocaleString(i18nLang));

  // Now that we have the total, loop through once more time to update percentages.
  counts.forEach(function (count) {
    // Calculate percentage, rounded to tenths.
    var percentage = getPercentage(count, total);

    // Update text with new value and percentage.
    $('.namespaces-table .sort-entry--count[data-value=' + count + ']').text(count.toLocaleString(i18nLang) + ' (' + percentage + ')');
  });

  // Loop through month and year charts, toggling the dataset for the newly excluded namespace.
  ['year', 'month'].forEach(function (id) {
    var chartObj = window[id + 'countsChart'],
      nsName = window.namespaces[key] || $.i18n('mainspace');

    // Year and month sections can be selectively hidden.
    if (!chartObj) {
      return;
    }

    // Figure out the index of the namespace we're toggling within this chart object.
    var datasetIndex = 0;
    chartObj.data.datasets.forEach(function (dataset, i) {
      if (dataset.label === nsName) {
        datasetIndex = i;
      }
    });

    // Fetch the metadata and toggle the hidden property.
    var meta = chartObj.getDatasetMeta(datasetIndex);
    meta.hidden = meta.hidden === null ? !chartObj.data.datasets[datasetIndex].hidden : null;

    // Add this namespace to the list of excluded namespaces.
    if (meta.hidden) {
      xtools.editcounter.excludedNamespaces.push(nsName);
    } else {
      xtools.editcounter.excludedNamespaces = xtools.editcounter.excludedNamespaces.filter(function (namespace) {
        return namespace !== nsName;
      });
    }

    // Update y-axis labels with the new totals.
    window[id + 'countsChart'].config.data.labels = getYAxisLabels(id, chartObj.data.datasets);

    // Refresh chart.
    chartObj.update();
  });
}

/**
 * Build the labels for the y-axis of the year/monthcount charts, which include the year/month and the total number of
 * edits across all namespaces in that year/month.
 * @param {String} id ID prefix of the chart, either 'month' or 'year'.
 * @param {Array} datasets Datasets making up the chart.
 * @return {Array} Labels for each year/month.
 */
function getYAxisLabels(id, datasets) {
  var labelsAndTotals = getMonthYearTotals(id, datasets);

  // Format labels with totals next to them. This is a bit hacky, but it works! We use tabs (\t) to make the
  // labels/totals for each namespace line up perfectly. The caveat is that we can't localize the numbers because
  // the commas are not monospaced :(
  return Object.keys(labelsAndTotals).map(function (year) {
    var digitCount = labelsAndTotals[year].toString().length;
    var numTabs = (xtools.editcounter.maxDigits[id] - digitCount) * 2;

    // +5 for a bit of extra spacing.
    /** global: i18nLang */
    return year + Array(numTabs + 5).join("\t") + labelsAndTotals[year].toLocaleString(i18nLang, {
      useGrouping: false
    });
  });
}

/**
 * Get the total number of edits for the given dataset (year or month).
 * @param {String} id ID prefix of the chart, either 'month' or 'year'.
 * @param {Array} datasets Datasets making up the chart.
 * @return {Object} Labels for each year/month as keys, totals as the values.
 */
function getMonthYearTotals(id, datasets) {
  var labelsAndTotals = {};
  datasets.forEach(function (namespace) {
    if (xtools.editcounter.excludedNamespaces.indexOf(namespace.label) !== -1) {
      return;
    }
    namespace.data.forEach(function (count, index) {
      if (!labelsAndTotals[xtools.editcounter.chartLabels[id][index]]) {
        labelsAndTotals[xtools.editcounter.chartLabels[id][index]] = 0;
      }
      labelsAndTotals[xtools.editcounter.chartLabels[id][index]] += count;
    });
  });
  return labelsAndTotals;
}

/**
 * Calculate and format a percentage, rounded to the tenths place.
 * @param {Number} numerator
 * @param {Number} denominator
 * @return {Number}
 */
function getPercentage(numerator, denominator) {
  /** global: i18nLang */
  return (numerator / denominator).toLocaleString(i18nLang, {
    style: 'percent'
  });
}

/**
 * Set up the monthcounts or yearcounts chart. This is set on the window
 * because it is called in the yearcounts/monthcounts view.
 * @param {String} id 'year' or 'month'.
 * @param {Array} datasets Datasets grouped by mainspace.
 * @param {Array} labels The bare labels for the y-axis (years or months).
 * @param {Number} maxTotal Maximum value of year/month totals.
 * @param {Boolean} showLegend Whether to show the legend above the chart.
 */
xtools.editcounter.setupMonthYearChart = function (id, datasets, labels, maxTotal, showLegend) {
  /** @type {Array} Labels for each namespace. */
  var namespaces = datasets.map(function (dataset) {
    return dataset.label;
  });
  xtools.editcounter.maxDigits[id] = maxTotal.toString().length;
  xtools.editcounter.chartLabels[id] = labels;

  /** global: i18nRTL */
  /** global: i18nLang */
  window[id + 'countsChart'] = new Chart($('#' + id + 'counts-canvas'), {
    type: 'horizontalBar',
    data: {
      labels: getYAxisLabels(id, datasets),
      datasets: datasets
    },
    options: {
      tooltips: {
        mode: 'nearest',
        intersect: true,
        callbacks: {
          label: function label(tooltip) {
            var labelsAndTotals = getMonthYearTotals(id, datasets),
              totals = Object.keys(labelsAndTotals).map(function (label) {
                return labelsAndTotals[label];
              }),
              total = totals[tooltip.index],
              percentage = getPercentage(tooltip.xLabel, total);
            return tooltip.xLabel.toLocaleString(i18nLang) + ' ' + '(' + percentage + ')';
          },
          title: function title(tooltip) {
            var yLabel = tooltip[0].yLabel.replace(/\t.*/, '');
            return yLabel + ' - ' + namespaces[tooltip[0].datasetIndex];
          }
        }
      },
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        xAxes: [{
          stacked: true,
          ticks: {
            beginAtZero: true,
            reverse: i18nRTL,
            callback: function callback(value) {
              if (Math.floor(value) === value) {
                return value.toLocaleString(i18nLang);
              }
            }
          },
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }],
        yAxes: [{
          stacked: true,
          barThickness: 18,
          position: i18nRTL ? 'right' : 'left',
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }]
      },
      legend: {
        display: showLegend
      }
    }
  });
};

/**
 * Builds the timecard chart and adds a listener for the 'local time' option.
 * @param {Array} timeCardDatasets
 * @param {Object} days
 */
xtools.editcounter.setupTimecard = function (timeCardDatasets, days) {
  var useLocalTimezone = false,
    timezoneOffset = new Date().getTimezoneOffset() / 60;
  window.chart = new Chart($("#timecard-bubble-chart"), {
    type: 'bubble',
    data: {
      datasets: timeCardDatasets
    },
    options: {
      responsive: true,
      // maintainAspectRatio: false,
      legend: {
        display: false
      },
      layout: {
        padding: {
          right: 0
        }
      },
      elements: {
        point: {
          radius: function radius(context) {
            var index = context.dataIndex;
            var data = context.dataset.data[index];
            // var size = context.chart.width;
            // var base = data.value / 100;
            // return (size / 50) * base;
            return data.scale;
          },
          hitRadius: 8
        }
      },
      scales: {
        yAxes: [{
          ticks: {
            min: 0,
            max: 8,
            stepSize: 1,
            padding: 25,
            callback: function callback(value, index) {
              return days[index];
            }
          },
          position: i18nRTL ? 'right' : 'left',
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }, {
          ticks: {
            min: 0,
            max: 8,
            stepSize: 1,
            padding: 25,
            callback: function callback(value, index) {
              if (index === 0 || index > 7) {
                return '';
              }
              return timeCardDatasets[index - 1].data.reduce(function (a, b) {
                return a + parseInt(b.value, 10);
              }, 0);
            }
          },
          position: i18nRTL ? 'left' : 'right'
        }],
        xAxes: [{
          ticks: {
            beginAtZero: true,
            min: 0,
            max: 23,
            stepSize: 1,
            reverse: i18nRTL,
            padding: 0,
            callback: function callback(value) {
              if (value % 2 === 0) {
                return value + ":00";
              } else {
                return '';
              }
            }
          },
          gridLines: {
            color: xtools.application.chartGridColor
          }
        }]
      },
      tooltips: {
        displayColors: false,
        callbacks: {
          title: function title(items) {
            return days[7 - items[0].yLabel + 1] + ' ' + items[0].xLabel + ':00';
          },
          label: function label(item) {
            var numEdits = [timeCardDatasets[item.datasetIndex].data[item.index].value];
            return "".concat(numEdits, " ").concat($.i18n('num-edits', [numEdits]));
          }
        }
      }
    }
  });
  $(function () {
    $('.use-local-time').prop('checked', false).on('click', function () {
      var offset = $(this).is(':checked') ? timezoneOffset : -timezoneOffset;
      chart.data.datasets = chart.data.datasets.map(function (day) {
        day.data = day.data.map(function (datum) {
          var newHour = (parseInt(datum.hour, 10) - offset) % 24;
          if (newHour < 0) {
            newHour = 24 + newHour;
          }
          datum.hour = newHour.toString();
          datum.x = newHour.toString();
          return datum;
        });
        return day;
      });
      useLocalTimezone = true;
      chart.update();
    });
  });
};

/***/ }),

/***/ "./assets/js/globalcontribs.js":
/*!*************************************!*\
  !*** ./assets/js/globalcontribs.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "./node_modules/core-js/modules/es.array.concat.js");
xtools.globalcontribs = {};
$(function () {
  // Don't do anything if this isn't a Global Contribs page.
  if ($('body.globalcontribs').length === 0) {
    return;
  }
  xtools.application.setupContributionsNavListeners(function (params) {
    return "globalcontribs/".concat(params.username, "/").concat(params.namespace, "/").concat(params.start, "/").concat(params.end);
  }, 'globalcontribs');
});

/***/ }),

/***/ "./assets/js/pages.js":
/*!****************************!*\
  !*** ./assets/js/pages.js ***!
  \****************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
__webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
__webpack_require__(/*! core-js/modules/es.object.keys.js */ "./node_modules/core-js/modules/es.object.keys.js");
__webpack_require__(/*! core-js/modules/es.number.to-fixed.js */ "./node_modules/core-js/modules/es.number.to-fixed.js");
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
__webpack_require__(/*! core-js/modules/es.date.to-string.js */ "./node_modules/core-js/modules/es.date.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.date.to-iso-string.js */ "./node_modules/core-js/modules/es.date.to-iso-string.js");
xtools.pages = {};
$(function () {
  // Don't execute this code if we're not on the Pages tool
  // FIXME: find a way to automate this somehow...
  if (!$('body.pages').length) {
    return;
  }
  var deletionSummaries = {};
  xtools.application.setupToggleTable(window.countsByNamespace, window.pieChart, 'count', function (newData) {
    var totals = {
      count: 0,
      deleted: 0,
      redirects: 0
    };
    Object.keys(newData).forEach(function (ns) {
      totals.count += newData[ns].count;
      totals.deleted += newData[ns].deleted;
      totals.redirects += newData[ns].redirects;
    });
    $('.namespaces--namespaces').text(Object.keys(newData).length.toLocaleString() + " " + $.i18n('num-namespaces', Object.keys(newData).length));
    $('.namespaces--pages').text(totals.count.toLocaleString());
    $('.namespaces--deleted').text(totals.deleted.toLocaleString() + " (" + (totals.deleted / totals.count * 100).toFixed(1) + "%)");
    $('.namespaces--redirects').text(totals.redirects.toLocaleString() + " (" + (totals.redirects / totals.count * 100).toFixed(1) + "%)");
  });
  $('.deleted-page').on('mouseover', function (e) {
    var page = $(this).data('page'),
      startTime = $(this).data('datetime').toString().slice(0, -2);
    var showSummary = function showSummary(summary) {
      $(e.target).find('.tooltip-body').html(summary);
    };
    if (deletionSummaries[page] !== undefined) {
      return showSummary(deletionSummaries[page]);
    }
    var logEventsQuery = function logEventsQuery(action) {
      return $.ajax({
        url: wikiApi,
        data: {
          action: 'query',
          list: 'logevents',
          letitle: page,
          lestart: startTime,
          letype: 'delete',
          leaction: action || 'delete/delete',
          lelimit: 1,
          format: 'json'
        },
        dataType: 'jsonp'
      });
    };
    var showParserApiFailure = function showParserApiFailure() {
      return showSummary("<span class='text-danger'>" + $.i18n('api-error', 'Parser API') + "</span>");
    };
    var showLoggingApiFailure = function showLoggingApiFailure() {
      return showSummary("<span class='text-danger'>" + $.i18n('api-error', 'Logging API') + "</span>");
    };
    var showParsedWikitext = function showParsedWikitext(event) {
      return $.ajax({
        url: xtBaseUrl + 'api/project/parser/' + wikiDomain + '?wikitext=' + encodeURIComponent(event.comment)
      }).done(function (markup) {
        // Get timestamp in YYYY-MM-DD HH:MM format.
        var timestamp = new Date(event.timestamp).toISOString().slice(0, 16).replace('T', ' ');

        // Add timestamp and link to admin.
        var summary = timestamp + " (<a target='_blank' href='https://" + wikiDomain + "/wiki/User:" + event.user + "'>" + event.user + '</a>): <i>' + markup + '</i>';
        deletionSummaries[page] = summary;
        showSummary(summary);
      }).fail(showParserApiFailure);
    };
    logEventsQuery().done(function (resp) {
      var event = resp.query.logevents[0];
      if (!event) {
        // Try again but look for redirect deletions.
        return logEventsQuery('delete/delete_redir').done(function (resp) {
          event = resp.query.logevents[0];
          if (!event) {
            return showParserApiFailure();
          }
          showParsedWikitext(event);
        }).fail(showLoggingApiFailure);
      }
      showParsedWikitext(event);
    }).fail(showLoggingApiFailure);
  });
});

/***/ }),

/***/ "./assets/js/topedits.js":
/*!*******************************!*\
  !*** ./assets/js/topedits.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
xtools.topedits = {};
$(function () {
  // Don't execute this code if we're not on the TopEdits tool.
  // FIXME: find a way to automate this somehow...
  if (!$('body.topedits').length) {
    return;
  }

  // Disable the article input if they select the 'All' namespace option
  $('#namespace_select').on('change', function () {
    $('#article_input').prop('disabled', $(this).val() === 'all');
  });
});

/***/ }),

/***/ "./assets/vendor/bootstrap-typeahead.js":
/*!**********************************************!*\
  !*** ./assets/vendor/bootstrap-typeahead.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

/* provided dependency */ var __webpack_provided_window_dot_jQuery = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.string.trim.js */ "./node_modules/core-js/modules/es.string.trim.js");
__webpack_require__(/*! core-js/modules/web.timers.js */ "./node_modules/core-js/modules/web.timers.js");
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
__webpack_require__(/*! core-js/modules/es.array.index-of.js */ "./node_modules/core-js/modules/es.array.index-of.js");
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "./node_modules/core-js/modules/es.array.concat.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.symbol.js */ "./node_modules/core-js/modules/es.symbol.js");
__webpack_require__(/*! core-js/modules/es.symbol.description.js */ "./node_modules/core-js/modules/es.symbol.description.js");
__webpack_require__(/*! core-js/modules/es.symbol.iterator.js */ "./node_modules/core-js/modules/es.symbol.iterator.js");
__webpack_require__(/*! core-js/modules/es.array.iterator.js */ "./node_modules/core-js/modules/es.array.iterator.js");
__webpack_require__(/*! core-js/modules/es.string.iterator.js */ "./node_modules/core-js/modules/es.string.iterator.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.iterator.js */ "./node_modules/core-js/modules/web.dom-collections.iterator.js");
/*!
 * bootstrap-typeahead.js v0.0.5 (http://www.upbootstrap.com)
 * Copyright 2012-2015 Twitter Inc.
 * Licensed under MIT (https://github.com/biggora/bootstrap-ajax-typeahead/blob/master/LICENSE)
 * See Demo: http://plugins.upbootstrap.com/bootstrap-ajax-typeahead
 * Updated: 2015-04-05 11:43:56
 *
 * Modifications by Paul Warelis and Alexey Gordeyev
 */
!function ($) {
  "use strict";

  // jshint ;_;

  /* TYPEAHEAD PUBLIC CLASS DEFINITION
   * ================================= */
  var Typeahead = function Typeahead(element, options) {
    //deal with scrollBar
    var defaultOptions = $.fn.typeahead.defaults;
    if (options.scrollBar) {
      options.items = 100;
      options.menu = '<ul class="typeahead dropdown-menu" style="max-height:220px;overflow:auto;"></ul>';
    }
    var that = this;
    that.$element = $(element);
    that.options = $.extend({}, $.fn.typeahead.defaults, options);
    that.$menu = $(that.options.menu).insertAfter(that.$element);

    // Method overrides
    that.eventSupported = that.options.eventSupported || that.eventSupported;
    that.grepper = that.options.grepper || that.grepper;
    that.highlighter = that.options.highlighter || that.highlighter;
    that.lookup = that.options.lookup || that.lookup;
    that.matcher = that.options.matcher || that.matcher;
    that.render = that.options.render || that.render;
    that.onSelect = that.options.onSelect || null;
    that.sorter = that.options.sorter || that.sorter;
    that.source = that.options.source || that.source;
    that.displayField = that.options.displayField || that.displayField;
    that.valueField = that.options.valueField || that.valueField;
    if (that.options.ajax) {
      var ajax = that.options.ajax;
      if (typeof ajax === 'string') {
        that.ajax = $.extend({}, $.fn.typeahead.defaults.ajax, {
          url: ajax
        });
      } else {
        if (typeof ajax.displayField === 'string') {
          that.displayField = that.options.displayField = ajax.displayField;
        }
        if (typeof ajax.valueField === 'string') {
          that.valueField = that.options.valueField = ajax.valueField;
        }
        that.ajax = $.extend({}, $.fn.typeahead.defaults.ajax, ajax);
      }
      if (!that.ajax.url) {
        that.ajax = null;
      }
      that.query = "";
    } else {
      that.source = that.options.source;
      that.ajax = null;
    }
    that.shown = false;
    that.listen();
  };
  Typeahead.prototype = {
    constructor: Typeahead,
    //=============================================================================================================
    //  Utils
    //  Check if an event is supported by the browser eg. 'keypress'
    //  * This was included to handle the "exhaustive deprecation" of jQuery.browser in jQuery 1.8
    //=============================================================================================================
    eventSupported: function eventSupported(eventName) {
      var isSupported = (eventName in this.$element);
      if (!isSupported) {
        this.$element.setAttribute(eventName, 'return;');
        isSupported = typeof this.$element[eventName] === 'function';
      }
      return isSupported;
    },
    select: function select() {
      var $selectedItem = this.$menu.find('.active');
      var value = $selectedItem.attr('data-value');
      var text = this.$menu.find('.active a').text();
      if (this.options.onSelect) {
        this.options.onSelect({
          value: value,
          text: text
        });
      }
      this.$element.val(this.updater(text)).change();
      return this.hide();
    },
    updater: function updater(item) {
      return item;
    },
    show: function show() {
      var pos = $.extend({}, this.$element.position(), {
        height: this.$element[0].offsetHeight
      });
      this.$menu.css({
        top: pos.top + pos.height,
        left: pos.left
      });
      if (this.options.alignWidth) {
        var width = $(this.$element[0]).outerWidth();
        this.$menu.css({
          width: width
        });
      }
      this.$menu.show();
      this.shown = true;
      return this;
    },
    hide: function hide() {
      this.$menu.hide();
      this.shown = false;
      return this;
    },
    ajaxLookup: function ajaxLookup() {
      var query = $.trim(this.$element.val());
      if (query === this.query) {
        return this;
      }

      // Query changed
      this.query = query;

      // Cancel last timer if set
      if (this.ajax.timerId) {
        clearTimeout(this.ajax.timerId);
        this.ajax.timerId = null;
      }
      if (!query || query.length < this.ajax.triggerLength) {
        // cancel the ajax callback if in progress
        if (this.ajax.xhr) {
          this.ajax.xhr.abort();
          this.ajax.xhr = null;
          this.ajaxToggleLoadClass(false);
        }
        return this.shown ? this.hide() : this;
      }
      function execute() {
        this.ajaxToggleLoadClass(true);

        // Cancel last call if already in progress
        if (this.ajax.xhr) this.ajax.xhr.abort();
        var params = this.ajax.preDispatch ? this.ajax.preDispatch(query) : {
          query: query
        };
        this.ajax.xhr = $.ajax({
          url: this.ajax.url,
          data: params,
          success: $.proxy(this.ajaxSource, this),
          type: this.ajax.method || 'get',
          dataType: 'jsonp'
        });
        this.ajax.timerId = null;
      }

      // Query is good to send, set a timer
      this.ajax.timerId = setTimeout($.proxy(execute, this), this.ajax.timeout);
      return this;
    },
    ajaxSource: function ajaxSource(data) {
      this.ajaxToggleLoadClass(false);
      var that = this,
        items;
      if (!that.ajax.xhr) return;
      if (that.ajax.preProcess) {
        data = that.ajax.preProcess(data);
      }
      // Save for selection retreival
      that.ajax.data = data;

      // Manipulate objects
      items = that.grepper(that.ajax.data) || [];
      if (!items.length) {
        return that.shown ? that.hide() : that;
      }
      that.ajax.xhr = null;
      return that.render(items.slice(0, that.options.items)).show();
    },
    ajaxToggleLoadClass: function ajaxToggleLoadClass(enable) {
      if (!this.ajax.loadingClass) return;
      this.$element.toggleClass(this.ajax.loadingClass, enable);
    },
    lookup: function lookup(event) {
      var that = this,
        items;
      if (that.ajax) {
        that.ajaxer();
      } else {
        that.query = that.$element.val();
        if (!that.query) {
          return that.shown ? that.hide() : that;
        }
        items = that.grepper(that.source);
        if (!items) {
          return that.shown ? that.hide() : that;
        }
        //Bhanu added a custom message- Result not Found when no result is found
        if (items.length == 0) {
          items[0] = {
            'id': -21,
            'name': "Result not Found"
          };
        }
        return that.render(items.slice(0, that.options.items)).show();
      }
    },
    matcher: function matcher(item) {
      return ~item.toLowerCase().indexOf(this.query.toLowerCase());
    },
    sorter: function sorter(items) {
      if (!this.options.ajax) {
        var beginswith = [],
          caseSensitive = [],
          caseInsensitive = [],
          item;
        while (item = items.shift()) {
          if (!item.toLowerCase().indexOf(this.query.toLowerCase())) beginswith.push(item);else if (~item.indexOf(this.query)) caseSensitive.push(item);else caseInsensitive.push(item);
        }
        return beginswith.concat(caseSensitive, caseInsensitive);
      } else {
        return items;
      }
    },
    highlighter: function highlighter(item) {
      var query = this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
      return item.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
        return '<strong>' + match + '</strong>';
      });
    },
    render: function render(items) {
      var that = this,
        display,
        isString = typeof that.options.displayField === 'string';
      items = $(items).map(function (i, item) {
        if (_typeof(item) === 'object') {
          display = isString ? item[that.options.displayField] : that.options.displayField(item);
          i = $(that.options.item).attr('data-value', item[that.options.valueField]);
        } else {
          display = item;
          i = $(that.options.item).attr('data-value', item);
        }
        i.find('a').html(that.highlighter(display));
        return i[0];
      });
      items.first().addClass('active');
      this.$menu.html(items);
      return this;
    },
    //------------------------------------------------------------------
    //  Filters relevent results
    //
    grepper: function grepper(data) {
      var that = this,
        items,
        display,
        isString = typeof that.options.displayField === 'string';
      if (isString && data && data.length) {
        if (data[0].hasOwnProperty(that.options.displayField)) {
          items = $.grep(data, function (item) {
            display = isString ? item[that.options.displayField] : that.options.displayField(item);
            return that.matcher(display);
          });
        } else if (typeof data[0] === 'string') {
          items = $.grep(data, function (item) {
            return that.matcher(item);
          });
        } else {
          return null;
        }
      } else {
        return null;
      }
      return this.sorter(items);
    },
    next: function next(event) {
      var active = this.$menu.find('.active').removeClass('active'),
        next = active.next();
      if (!next.length) {
        next = $(this.$menu.find('li')[0]);
      }
      if (this.options.scrollBar) {
        var index = this.$menu.children("li").index(next);
        if (index % 8 == 0) {
          this.$menu.scrollTop(index * 26);
        }
      }
      next.addClass('active');
    },
    prev: function prev(event) {
      var active = this.$menu.find('.active').removeClass('active'),
        prev = active.prev();
      if (!prev.length) {
        prev = this.$menu.find('li').last();
      }
      if (this.options.scrollBar) {
        var $li = this.$menu.children("li");
        var total = $li.length - 1;
        var index = $li.index(prev);
        if ((total - index) % 8 == 0) {
          this.$menu.scrollTop((index - 7) * 26);
        }
      }
      prev.addClass('active');
    },
    listen: function listen() {
      this.$element.on('focus', $.proxy(this.focus, this)).on('blur', $.proxy(this.blur, this)).on('keypress', $.proxy(this.keypress, this)).on('keyup', $.proxy(this.keyup, this));
      if (this.eventSupported('keydown')) {
        this.$element.on('keydown', $.proxy(this.keydown, this));
      }
      this.$menu.on('click', $.proxy(this.click, this)).on('mouseenter', 'li', $.proxy(this.mouseenter, this)).on('mouseleave', 'li', $.proxy(this.mouseleave, this));
    },
    move: function move(e) {
      if (!this.shown) return;
      switch (e.keyCode) {
        case 9: // tab
        case 13: // enter
        case 27:
          // escape
          e.preventDefault();
          break;
        case 38:
          // up arrow
          e.preventDefault();
          this.prev();
          break;
        case 40:
          // down arrow
          e.preventDefault();
          this.next();
          break;
      }
      e.stopPropagation();
    },
    keydown: function keydown(e) {
      this.suppressKeyPressRepeat = ~$.inArray(e.keyCode, [40, 38, 9, 13, 27]);
      this.move(e);
    },
    keypress: function keypress(e) {
      if (this.suppressKeyPressRepeat) return;
      this.move(e);
    },
    keyup: function keyup(e) {
      switch (e.keyCode) {
        case 40: // down arrow
        case 38: // up arrow
        case 16: // shift
        case 17: // ctrl
        case 18:
          // alt
          break;
        case 9: // tab
        case 13:
          // enter
          if (!this.shown) return;
          this.select();
          break;
        case 27:
          // escape
          if (!this.shown) return;
          this.hide();
          break;
        default:
          if (this.ajax) this.ajaxLookup();else this.lookup();
      }
      e.stopPropagation();
      e.preventDefault();
    },
    focus: function focus(e) {
      this.focused = true;
    },
    blur: function blur(e) {
      this.focused = false;
      if (!this.mousedover && this.shown) this.hide();
    },
    click: function click(e) {
      e.stopPropagation();
      e.preventDefault();
      this.select();
      this.$element.focus();
    },
    mouseenter: function mouseenter(e) {
      this.mousedover = true;
      this.$menu.find('.active').removeClass('active');
      $(e.currentTarget).addClass('active');
    },
    mouseleave: function mouseleave(e) {
      this.mousedover = false;
      if (!this.focused && this.shown) this.hide();
    },
    destroy: function destroy() {
      this.$element.off('focus', $.proxy(this.focus, this)).off('blur', $.proxy(this.blur, this)).off('keypress', $.proxy(this.keypress, this)).off('keyup', $.proxy(this.keyup, this));
      if (this.eventSupported('keydown')) {
        this.$element.off('keydown', $.proxy(this.keydown, this));
      }
      this.$menu.off('click', $.proxy(this.click, this)).off('mouseenter', 'li', $.proxy(this.mouseenter, this)).off('mouseleave', 'li', $.proxy(this.mouseleave, this));
      this.$element.removeData('typeahead');
    }
  };

  /* TYPEAHEAD PLUGIN DEFINITION
   * =========================== */

  $.fn.typeahead = function (option) {
    return this.each(function () {
      var $this = $(this),
        data = $this.data('typeahead'),
        options = _typeof(option) === 'object' && option;
      if (!data) $this.data('typeahead', data = new Typeahead(this, options));
      if (typeof option === 'string') data[option]();
    });
  };
  $.fn.typeahead.defaults = {
    source: [],
    items: 10,
    scrollBar: false,
    alignWidth: true,
    menu: '<ul class="typeahead dropdown-menu"></ul>',
    item: '<li><a href="#"></a></li>',
    valueField: 'id',
    displayField: 'name',
    onSelect: function onSelect() {},
    ajax: {
      url: null,
      timeout: 300,
      method: 'get',
      triggerLength: 1,
      loadingClass: null,
      preDispatch: null,
      preProcess: null
    }
  };
  $.fn.typeahead.Constructor = Typeahead;

  /* TYPEAHEAD DATA-API
   * ================== */

  $(function () {
    $('body').on('focus.typeahead.data-api', '[data-provide="typeahead"]', function (e) {
      var $this = $(this);
      if ($this.data('typeahead')) return;
      e.preventDefault();
      $this.typeahead($this.data());
    });
  });
}(__webpack_provided_window_dot_jQuery);

/***/ }),

/***/ "./assets/vendor/jquery.i18n/jquery.i18n.dist.js":
/*!*******************************************************!*\
  !*** ./assets/vendor/jquery.i18n/jquery.i18n.dist.js ***!
  \*******************************************************/
/***/ (function(module, exports, __webpack_require__) {

/* provided dependency */ var jQuery = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
__webpack_require__(/*! core-js/modules/es.array.slice.js */ "./node_modules/core-js/modules/es.array.slice.js");
__webpack_require__(/*! core-js/modules/es.array.join.js */ "./node_modules/core-js/modules/es.array.join.js");
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "./node_modules/core-js/modules/es.array.concat.js");
__webpack_require__(/*! core-js/modules/es.array.index-of.js */ "./node_modules/core-js/modules/es.array.index-of.js");
__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "./node_modules/core-js/modules/es.regexp.exec.js");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "./node_modules/core-js/modules/es.string.replace.js");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "./node_modules/core-js/modules/es.parse-int.js");
__webpack_require__(/*! core-js/modules/es.string.match.js */ "./node_modules/core-js/modules/es.string.match.js");
__webpack_require__(/*! core-js/modules/es.date.to-string.js */ "./node_modules/core-js/modules/es.date.to-string.js");
__webpack_require__(/*! core-js/modules/es.regexp.to-string.js */ "./node_modules/core-js/modules/es.regexp.to-string.js");
__webpack_require__(/*! core-js/modules/es.array.map.js */ "./node_modules/core-js/modules/es.array.map.js");
__webpack_require__(/*! core-js/modules/es.parse-float.js */ "./node_modules/core-js/modules/es.parse-float.js");
__webpack_require__(/*! core-js/modules/es.regexp.constructor.js */ "./node_modules/core-js/modules/es.regexp.constructor.js");
__webpack_require__(/*! core-js/modules/es.symbol.js */ "./node_modules/core-js/modules/es.symbol.js");
__webpack_require__(/*! core-js/modules/es.symbol.description.js */ "./node_modules/core-js/modules/es.symbol.description.js");
__webpack_require__(/*! core-js/modules/es.symbol.iterator.js */ "./node_modules/core-js/modules/es.symbol.iterator.js");
__webpack_require__(/*! core-js/modules/es.array.iterator.js */ "./node_modules/core-js/modules/es.array.iterator.js");
__webpack_require__(/*! core-js/modules/es.string.iterator.js */ "./node_modules/core-js/modules/es.string.iterator.js");
__webpack_require__(/*! core-js/modules/web.dom-collections.iterator.js */ "./node_modules/core-js/modules/web.dom-collections.iterator.js");
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

(function ($) {
  'use strict';

  var nav,
    _I18N,
    slice = Array.prototype.slice;
  /**
   * @constructor
   * @param {Object} options
   */
  _I18N = function I18N(options) {
    // Load defaults
    this.options = $.extend({}, _I18N.defaults, options);
    this.parser = this.options.parser;
    this.locale = this.options.locale;
    this.messageStore = this.options.messageStore;
    this.languages = {};
    this.init();
  };
  _I18N.prototype = {
    /**
     * Initialize by loading locales and setting up
     * String.prototype.toLocaleString and String.locale.
     */
    init: function init() {
      var i18n = this;

      // Set locale of String environment
      String.locale = i18n.locale;

      // Override String.localeString method
      String.prototype.toLocaleString = function () {
        var localeParts, localePartIndex, value, locale, fallbackIndex, tryingLocale, message;
        value = this.valueOf();
        locale = i18n.locale;
        fallbackIndex = 0;
        while (locale) {
          // Iterate through locales starting at most-specific until
          // localization is found. As in fi-Latn-FI, fi-Latn and fi.
          localeParts = locale.split('-');
          localePartIndex = localeParts.length;
          do {
            tryingLocale = localeParts.slice(0, localePartIndex).join('-');
            message = i18n.messageStore.get(tryingLocale, value);
            if (message) {
              return message;
            }
            localePartIndex--;
          } while (localePartIndex);
          if (locale === 'en') {
            break;
          }
          locale = $.i18n.fallbacks[i18n.locale] && $.i18n.fallbacks[i18n.locale][fallbackIndex] || i18n.options.fallbackLocale;
          $.i18n.log('Trying fallback locale for ' + i18n.locale + ': ' + locale);
          fallbackIndex++;
        }

        // key not found
        return '';
      };
    },
    /*
     * Destroy the i18n instance.
     */
    destroy: function destroy() {
      $.removeData(document, 'i18n');
    },
    /**
     * General message loading API This can take a URL string for
     * the json formatted messages. Example:
     * <code>load('path/to/all_localizations.json');</code>
     *
     * To load a localization file for a locale:
     * <code>
     * load('path/to/de-messages.json', 'de' );
     * </code>
     *
     * To load a localization file from a directory:
     * <code>
     * load('path/to/i18n/directory', 'de' );
     * </code>
     * The above method has the advantage of fallback resolution.
     * ie, it will automatically load the fallback locales for de.
     * For most usecases, this is the recommended method.
     * It is optional to have trailing slash at end.
     *
     * A data object containing message key- message translation mappings
     * can also be passed. Example:
     * <code>
     * load( { 'hello' : 'Hello' }, optionalLocale );
     * </code>
     *
     * A source map containing key-value pair of languagename and locations
     * can also be passed. Example:
     * <code>
     * load( {
     * bn: 'i18n/bn.json',
     * he: 'i18n/he.json',
     * en: 'i18n/en.json'
     * } )
     * </code>
     *
     * If the data argument is null/undefined/false,
     * all cached messages for the i18n instance will get reset.
     *
     * @param {string|Object} source
     * @param {string} locale Language tag
     * @return {jQuery.Promise}
     */
    load: function load(source, locale) {
      var fallbackLocales,
        locIndex,
        fallbackLocale,
        sourceMap = {};
      if (!source && !locale) {
        source = 'i18n/' + $.i18n().locale + '.json';
        locale = $.i18n().locale;
      }
      if (typeof source === 'string' && source.split('.').pop() !== 'json') {
        // Load specified locale then check for fallbacks when directory is specified in load()
        sourceMap[locale] = source + '/' + locale + '.json';
        fallbackLocales = ($.i18n.fallbacks[locale] || []).concat(this.options.fallbackLocale);
        for (locIndex in fallbackLocales) {
          fallbackLocale = fallbackLocales[locIndex];
          sourceMap[fallbackLocale] = source + '/' + fallbackLocale + '.json';
        }
        return this.load(sourceMap);
      } else {
        return this.messageStore.load(source, locale);
      }
    },
    /**
     * Does parameter and magic word substitution.
     *
     * @param {string} key Message key
     * @param {Array} parameters Message parameters
     * @return {string}
     */
    parse: function parse(key, parameters) {
      var message = key.toLocaleString();
      // FIXME: This changes the state of the I18N object,
      // should probably not change the 'this.parser' but just
      // pass it to the parser.
      this.parser.language = $.i18n.languages[$.i18n().locale] || $.i18n.languages['default'];
      if (message === '') {
        message = key;
      }
      return this.parser.parse(message, parameters);
    }
  };

  /**
   * Process a message from the $.I18N instance
   * for the current document, stored in jQuery.data(document).
   *
   * @param {string} key Key of the message.
   * @param {string} param1 [param...] Variadic list of parameters for {key}.
   * @return {string|$.I18N} Parsed message, or if no key was given
   * the instance of $.I18N is returned.
   */
  $.i18n = function (key, param1) {
    var parameters,
      i18n = $.data(document, 'i18n'),
      options = _typeof(key) === 'object' && key;

    // If the locale option for this call is different then the setup so far,
    // update it automatically. This doesn't just change the context for this
    // call but for all future call as well.
    // If there is no i18n setup yet, don't do this. It will be taken care of
    // by the `new I18N` construction below.
    // NOTE: It should only change language for this one call.
    // Then cache instances of I18N somewhere.
    if (options && options.locale && i18n && i18n.locale !== options.locale) {
      String.locale = i18n.locale = options.locale;
    }
    if (!i18n) {
      i18n = new _I18N(options);
      $.data(document, 'i18n', i18n);
    }
    if (typeof key === 'string') {
      if (param1 !== undefined) {
        parameters = slice.call(arguments, 1);
      } else {
        parameters = [];
      }
      return i18n.parse(key, parameters);
    } else {
      // FIXME: remove this feature/bug.
      return i18n;
    }
  };
  $.fn.i18n = function () {
    var i18n = $.data(document, 'i18n');
    if (!i18n) {
      i18n = new _I18N();
      $.data(document, 'i18n', i18n);
    }
    String.locale = i18n.locale;
    return this.each(function () {
      var $this = $(this),
        messageKey = $this.data('i18n'),
        lBracket,
        rBracket,
        type,
        key;
      if (messageKey) {
        lBracket = messageKey.indexOf('[');
        rBracket = messageKey.indexOf(']');
        if (lBracket !== -1 && rBracket !== -1 && lBracket < rBracket) {
          type = messageKey.slice(lBracket + 1, rBracket);
          key = messageKey.slice(rBracket + 1);
          if (type === 'html') {
            $this.html(i18n.parse(key));
          } else {
            $this.attr(type, i18n.parse(key));
          }
        } else {
          $this.text(i18n.parse(messageKey));
        }
      } else {
        $this.find('[data-i18n]').i18n();
      }
    });
  };
  String.locale = String.locale || $('html').attr('lang');
  if (!String.locale) {
    if (_typeof(window.navigator) !== undefined) {
      nav = window.navigator;
      String.locale = nav.language || nav.userLanguage || '';
    } else {
      String.locale = '';
    }
  }
  $.i18n.languages = {};
  $.i18n.messageStore = $.i18n.messageStore || {};
  $.i18n.parser = {
    // The default parser only handles variable substitution
    parse: function parse(message, parameters) {
      return message.replace(/\$(\d+)/g, function (str, match) {
        var index = parseInt(match, 10) - 1;
        return parameters[index] !== undefined ? parameters[index] : '$' + match;
      });
    },
    emitter: {}
  };
  $.i18n.fallbacks = {};
  $.i18n.debug = false;
  $.i18n.log = function /* arguments */
  () {
    if (window.console && $.i18n.debug) {
      window.console.log.apply(window.console, arguments);
    }
  };
  /* Static members */
  _I18N.defaults = {
    locale: String.locale,
    fallbackLocale: 'en',
    parser: $.i18n.parser,
    messageStore: $.i18n.messageStore
  };

  // Expose constructor
  $.i18n.constructor = _I18N;
})(jQuery);
/*!
 * jQuery Internationalization library - Message Store
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do anything special to
 * choose one license or the other and you don't have to notify anyone which license you are using.
 * You are free to use UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

(function ($, window, undefined) {
  'use strict';

  var MessageStore = function MessageStore() {
    this.messages = {};
    this.sources = {};
  };

  /**
   * See https://github.com/wikimedia/jquery.i18n/wiki/Specification#wiki-Message_File_Loading
   */
  MessageStore.prototype = {
    /**
     * General message loading API This can take a URL string for
     * the json formatted messages.
     * <code>load('path/to/all_localizations.json');</code>
     *
     * This can also load a localization file for a locale <code>
     * load( 'path/to/de-messages.json', 'de' );
     * </code>
     * A data object containing message key- message translation mappings
     * can also be passed Eg:
     * <code>
     * load( { 'hello' : 'Hello' }, optionalLocale );
     * </code> If the data argument is
     * null/undefined/false,
     * all cached messages for the i18n instance will get reset.
     *
     * @param {string|Object} source
     * @param {string} locale Language tag
     * @return {jQuery.Promise}
     */
    load: function load(source, locale) {
      var key = null,
        deferred = null,
        deferreds = [],
        messageStore = this;
      if (typeof source === 'string') {
        // This is a URL to the messages file.
        $.i18n.log('Loading messages from: ' + source);
        deferred = jsonMessageLoader(source).done(function (localization) {
          messageStore.set(locale, localization);
        });
        return deferred.promise();
      }
      if (locale) {
        // source is an key-value pair of messages for given locale
        messageStore.set(locale, source);
        return $.Deferred().resolve();
      } else {
        // source is a key-value pair of locales and their source
        for (key in source) {
          if (Object.prototype.hasOwnProperty.call(source, key)) {
            locale = key;
            // No {locale} given, assume data is a group of languages,
            // call this function again for each language.
            deferreds.push(messageStore.load(source[key], locale));
          }
        }
        return $.when.apply($, deferreds);
      }
    },
    /**
     * Set messages to the given locale.
     * If locale exists, add messages to the locale.
     *
     * @param {string} locale
     * @param {Object} messages
     */
    set: function set(locale, messages) {
      if (!this.messages[locale]) {
        this.messages[locale] = messages;
      } else {
        this.messages[locale] = $.extend(this.messages[locale], messages);
      }
    },
    /**
     *
     * @param {string} locale
     * @param {string} messageKey
     * @return {boolean}
     */
    get: function get(locale, messageKey) {
      return this.messages[locale] && this.messages[locale][messageKey];
    }
  };
  function jsonMessageLoader(url) {
    var deferred = $.Deferred();
    $.getJSON(url).done(deferred.resolve).fail(function (jqxhr, settings, exception) {
      $.i18n.log('Error in loading messages from ' + url + ' Exception: ' + exception);
      // Ignore 404 exception, because we are handling fallabacks explicitly
      deferred.resolve();
    });
    return deferred.promise();
  }
  $.extend($.i18n.messageStore, new MessageStore());
})(jQuery, window);
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2012 Santhosh Thottingal
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do anything special to
 * choose one license or the other and you don't have to notify anyone which license you are using.
 * You are free to use UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */
(function ($, undefined) {
  'use strict';

  $.i18n = $.i18n || {};
  $.extend($.i18n.fallbacks, {
    ab: ['ru'],
    ace: ['id'],
    aln: ['sq'],
    // Not so standard - als is supposed to be Tosk Albanian,
    // but in Wikipedia it's used for a Germanic language.
    als: ['gsw', 'de'],
    an: ['es'],
    anp: ['hi'],
    arn: ['es'],
    arz: ['ar'],
    av: ['ru'],
    ay: ['es'],
    ba: ['ru'],
    bar: ['de'],
    'bat-smg': ['sgs', 'lt'],
    bcc: ['fa'],
    'be-x-old': ['be-tarask'],
    bh: ['bho'],
    bjn: ['id'],
    bm: ['fr'],
    bpy: ['bn'],
    bqi: ['fa'],
    bug: ['id'],
    'cbk-zam': ['es'],
    ce: ['ru'],
    crh: ['crh-latn'],
    'crh-cyrl': ['ru'],
    csb: ['pl'],
    cv: ['ru'],
    'de-at': ['de'],
    'de-ch': ['de'],
    'de-formal': ['de'],
    dsb: ['de'],
    dtp: ['ms'],
    egl: ['it'],
    eml: ['it'],
    ff: ['fr'],
    fit: ['fi'],
    'fiu-vro': ['vro', 'et'],
    frc: ['fr'],
    frp: ['fr'],
    frr: ['de'],
    fur: ['it'],
    gag: ['tr'],
    gan: ['gan-hant', 'zh-hant', 'zh-hans'],
    'gan-hans': ['zh-hans'],
    'gan-hant': ['zh-hant', 'zh-hans'],
    gl: ['pt'],
    glk: ['fa'],
    gn: ['es'],
    gsw: ['de'],
    hif: ['hif-latn'],
    hsb: ['de'],
    ht: ['fr'],
    ii: ['zh-cn', 'zh-hans'],
    inh: ['ru'],
    iu: ['ike-cans'],
    jut: ['da'],
    jv: ['id'],
    kaa: ['kk-latn', 'kk-cyrl'],
    kbd: ['kbd-cyrl'],
    khw: ['ur'],
    kiu: ['tr'],
    kk: ['kk-cyrl'],
    'kk-arab': ['kk-cyrl'],
    'kk-latn': ['kk-cyrl'],
    'kk-cn': ['kk-arab', 'kk-cyrl'],
    'kk-kz': ['kk-cyrl'],
    'kk-tr': ['kk-latn', 'kk-cyrl'],
    kl: ['da'],
    'ko-kp': ['ko'],
    koi: ['ru'],
    krc: ['ru'],
    ks: ['ks-arab'],
    ksh: ['de'],
    ku: ['ku-latn'],
    'ku-arab': ['ckb'],
    kv: ['ru'],
    lad: ['es'],
    lb: ['de'],
    lbe: ['ru'],
    lez: ['ru'],
    li: ['nl'],
    lij: ['it'],
    liv: ['et'],
    lmo: ['it'],
    ln: ['fr'],
    ltg: ['lv'],
    lzz: ['tr'],
    mai: ['hi'],
    'map-bms': ['jv', 'id'],
    mg: ['fr'],
    mhr: ['ru'],
    min: ['id'],
    mo: ['ro'],
    mrj: ['ru'],
    mwl: ['pt'],
    myv: ['ru'],
    mzn: ['fa'],
    nah: ['es'],
    nap: ['it'],
    nds: ['de'],
    'nds-nl': ['nl'],
    'nl-informal': ['nl'],
    no: ['nb'],
    os: ['ru'],
    pcd: ['fr'],
    pdc: ['de'],
    pdt: ['de'],
    pfl: ['de'],
    pms: ['it'],
    pt: ['pt-br'],
    'pt-br': ['pt'],
    qu: ['es'],
    qug: ['qu', 'es'],
    rgn: ['it'],
    rmy: ['ro'],
    'roa-rup': ['rup'],
    rue: ['uk', 'ru'],
    ruq: ['ruq-latn', 'ro'],
    'ruq-cyrl': ['mk'],
    'ruq-latn': ['ro'],
    sa: ['hi'],
    sah: ['ru'],
    scn: ['it'],
    sg: ['fr'],
    sgs: ['lt'],
    sli: ['de'],
    sr: ['sr-ec'],
    srn: ['nl'],
    stq: ['de'],
    su: ['id'],
    szl: ['pl'],
    tcy: ['kn'],
    tg: ['tg-cyrl'],
    tt: ['tt-cyrl', 'ru'],
    'tt-cyrl': ['ru'],
    ty: ['fr'],
    udm: ['ru'],
    ug: ['ug-arab'],
    uk: ['ru'],
    vec: ['it'],
    vep: ['et'],
    vls: ['nl'],
    vmf: ['de'],
    vot: ['fi'],
    vro: ['et'],
    wa: ['fr'],
    wo: ['fr'],
    wuu: ['zh-hans'],
    xal: ['ru'],
    xmf: ['ka'],
    yi: ['he'],
    za: ['zh-hans'],
    zea: ['nl'],
    zh: ['zh-hans'],
    'zh-classical': ['lzh'],
    'zh-cn': ['zh-hans'],
    'zh-hant': ['zh-hans'],
    'zh-hk': ['zh-hant', 'zh-hans'],
    'zh-min-nan': ['nan'],
    'zh-mo': ['zh-hk', 'zh-hant', 'zh-hans'],
    'zh-my': ['zh-sg', 'zh-hans'],
    'zh-sg': ['zh-hans'],
    'zh-tw': ['zh-hant', 'zh-hans'],
    'zh-yue': ['yue']
  });
})(jQuery);
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2011-2013 Santhosh Thottingal, Neil Kandalgaonkar
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

(function ($) {
  'use strict';

  var MessageParser = function MessageParser(options) {
    this.options = $.extend({}, $.i18n.parser.defaults, options);
    this.language = $.i18n.languages[String.locale] || $.i18n.languages['default'];
    this.emitter = $.i18n.parser.emitter;
  };
  MessageParser.prototype = {
    constructor: MessageParser,
    simpleParse: function simpleParse(message, parameters) {
      return message.replace(/\$(\d+)/g, function (str, match) {
        var index = parseInt(match, 10) - 1;
        return parameters[index] !== undefined ? parameters[index] : '$' + match;
      });
    },
    parse: function parse(message, replacements) {
      if (message.indexOf('{{') < 0) {
        return this.simpleParse(message, replacements);
      }
      this.emitter.language = $.i18n.languages[$.i18n().locale] || $.i18n.languages['default'];
      return this.emitter.emit(this.ast(message), replacements);
    },
    ast: function ast(message) {
      var pipe,
        colon,
        backslash,
        anyCharacter,
        dollar,
        digits,
        regularLiteral,
        regularLiteralWithoutBar,
        regularLiteralWithoutSpace,
        escapedOrLiteralWithoutBar,
        escapedOrRegularLiteral,
        templateContents,
        templateName,
        openTemplate,
        closeTemplate,
        expression,
        paramExpression,
        result,
        pos = 0;

      // Try parsers until one works, if none work return null
      function choice(parserSyntax) {
        return function () {
          var i, result;
          for (i = 0; i < parserSyntax.length; i++) {
            result = parserSyntax[i]();
            if (result !== null) {
              return result;
            }
          }
          return null;
        };
      }

      // Try several parserSyntax-es in a row.
      // All must succeed; otherwise, return null.
      // This is the only eager one.
      function sequence(parserSyntax) {
        var i,
          res,
          originalPos = pos,
          result = [];
        for (i = 0; i < parserSyntax.length; i++) {
          res = parserSyntax[i]();
          if (res === null) {
            pos = originalPos;
            return null;
          }
          result.push(res);
        }
        return result;
      }

      // Run the same parser over and over until it fails.
      // Must succeed a minimum of n times; otherwise, return null.
      function nOrMore(n, p) {
        return function () {
          var originalPos = pos,
            result = [],
            parsed = p();
          while (parsed !== null) {
            result.push(parsed);
            parsed = p();
          }
          if (result.length < n) {
            pos = originalPos;
            return null;
          }
          return result;
        };
      }

      // Helpers -- just make parserSyntax out of simpler JS builtin types

      function makeStringParser(s) {
        var len = s.length;
        return function () {
          var result = null;
          if (message.slice(pos, pos + len) === s) {
            result = s;
            pos += len;
          }
          return result;
        };
      }
      function makeRegexParser(regex) {
        return function () {
          var matches = message.slice(pos).match(regex);
          if (matches === null) {
            return null;
          }
          pos += matches[0].length;
          return matches[0];
        };
      }
      pipe = makeStringParser('|');
      colon = makeStringParser(':');
      backslash = makeStringParser('\\');
      anyCharacter = makeRegexParser(/^./);
      dollar = makeStringParser('$');
      digits = makeRegexParser(/^\d+/);
      regularLiteral = makeRegexParser(/^[^{}\[\]$\\]/);
      regularLiteralWithoutBar = makeRegexParser(/^[^{}\[\]$\\|]/);
      regularLiteralWithoutSpace = makeRegexParser(/^[^{}\[\]$\s]/);

      // There is a general pattern:
      // parse a thing;
      // if it worked, apply transform,
      // otherwise return null.
      // But using this as a combinator seems to cause problems
      // when combined with nOrMore().
      // May be some scoping issue.
      function transform(p, fn) {
        return function () {
          var result = p();
          return result === null ? null : fn(result);
        };
      }

      // Used to define "literals" within template parameters. The pipe
      // character is the parameter delimeter, so by default
      // it is not a literal in the parameter
      function literalWithoutBar() {
        var result = nOrMore(1, escapedOrLiteralWithoutBar)();
        return result === null ? null : result.join('');
      }
      function literal() {
        var result = nOrMore(1, escapedOrRegularLiteral)();
        return result === null ? null : result.join('');
      }
      function escapedLiteral() {
        var result = sequence([backslash, anyCharacter]);
        return result === null ? null : result[1];
      }
      choice([escapedLiteral, regularLiteralWithoutSpace]);
      escapedOrLiteralWithoutBar = choice([escapedLiteral, regularLiteralWithoutBar]);
      escapedOrRegularLiteral = choice([escapedLiteral, regularLiteral]);
      function replacement() {
        var result = sequence([dollar, digits]);
        if (result === null) {
          return null;
        }
        return ['REPLACE', parseInt(result[1], 10) - 1];
      }
      templateName = transform(
      // see $wgLegalTitleChars
      // not allowing : due to the need to catch "PLURAL:$1"
      makeRegexParser(/^[ !"$&'()*,.\/0-9;=?@A-Z\^_`a-z~\x80-\xFF+\-]+/), function (result) {
        return result.toString();
      });
      function templateParam() {
        var expr,
          result = sequence([pipe, nOrMore(0, paramExpression)]);
        if (result === null) {
          return null;
        }
        expr = result[1];

        // use a "CONCAT" operator if there are multiple nodes,
        // otherwise return the first node, raw.
        return expr.length > 1 ? ['CONCAT'].concat(expr) : expr[0];
      }
      function templateWithReplacement() {
        var result = sequence([templateName, colon, replacement]);
        return result === null ? null : [result[0], result[2]];
      }
      function templateWithOutReplacement() {
        var result = sequence([templateName, colon, paramExpression]);
        return result === null ? null : [result[0], result[2]];
      }
      templateContents = choice([function () {
        var res = sequence([
        // templates can have placeholders for dynamic
        // replacement eg: {{PLURAL:$1|one car|$1 cars}}
        // or no placeholders eg:
        // {{GRAMMAR:genitive|{{SITENAME}}}
        choice([templateWithReplacement, templateWithOutReplacement]), nOrMore(0, templateParam)]);
        return res === null ? null : res[0].concat(res[1]);
      }, function () {
        var res = sequence([templateName, nOrMore(0, templateParam)]);
        if (res === null) {
          return null;
        }
        return [res[0]].concat(res[1]);
      }]);
      openTemplate = makeStringParser('{{');
      closeTemplate = makeStringParser('}}');
      function template() {
        var result = sequence([openTemplate, templateContents, closeTemplate]);
        return result === null ? null : result[1];
      }
      expression = choice([template, replacement, literal]);
      paramExpression = choice([template, replacement, literalWithoutBar]);
      function start() {
        var result = nOrMore(0, expression)();
        if (result === null) {
          return null;
        }
        return ['CONCAT'].concat(result);
      }
      result = start();

      /*
       * For success, the pos must have gotten to the end of the input
       * and returned a non-null.
       * n.b. This is part of language infrastructure, so we do not throw an internationalizable message.
       */
      if (result === null || pos !== message.length) {
        throw new Error('Parse error at position ' + pos.toString() + ' in input: ' + message);
      }
      return result;
    }
  };
  $.extend($.i18n.parser, new MessageParser());
})(jQuery);
/*!
 * jQuery Internationalization library
 *
 * Copyright (C) 2011-2013 Santhosh Thottingal, Neil Kandalgaonkar
 *
 * jquery.i18n is dual licensed GPLv2 or later and MIT. You don't have to do
 * anything special to choose one license or the other and you don't have to
 * notify anyone which license you are using. You are free to use
 * UniversalLanguageSelector in commercial projects as long as the copyright
 * header is left intact. See files GPL-LICENSE and MIT-LICENSE for details.
 *
 * @licence GNU General Public Licence 2.0 or later
 * @licence MIT License
 */

(function ($) {
  'use strict';

  var MessageParserEmitter = function MessageParserEmitter() {
    this.language = $.i18n.languages[String.locale] || $.i18n.languages['default'];
  };
  MessageParserEmitter.prototype = {
    constructor: MessageParserEmitter,
    /**
     * (We put this method definition here, and not in prototype, to make
     * sure it's not overwritten by any magic.) Walk entire node structure,
     * applying replacements and template functions when appropriate
     *
     * @param {Mixed} node abstract syntax tree (top node or subnode)
     * @param {Array} replacements for $1, $2, ... $n
     * @return {Mixed} single-string node or array of nodes suitable for
     *  jQuery appending.
     */
    emit: function emit(node, replacements) {
      var ret,
        subnodes,
        operation,
        messageParserEmitter = this;
      switch (_typeof(node)) {
        case 'string':
        case 'number':
          ret = node;
          break;
        case 'object':
          // node is an array of nodes
          subnodes = $.map(node.slice(1), function (n) {
            return messageParserEmitter.emit(n, replacements);
          });
          operation = node[0].toLowerCase();
          if (typeof messageParserEmitter[operation] === 'function') {
            ret = messageParserEmitter[operation](subnodes, replacements);
          } else {
            throw new Error('unknown operation "' + operation + '"');
          }
          break;
        case 'undefined':
          // Parsing the empty string (as an entire expression, or as a
          // paramExpression in a template) results in undefined
          // Perhaps a more clever parser can detect this, and return the
          // empty string? Or is that useful information?
          // The logical thing is probably to return the empty string here
          // when we encounter undefined.
          ret = '';
          break;
        default:
          throw new Error('unexpected type in AST: ' + _typeof(node));
      }
      return ret;
    },
    /**
     * Parsing has been applied depth-first we can assume that all nodes
     * here are single nodes Must return a single node to parents -- a
     * jQuery with synthetic span However, unwrap any other synthetic spans
     * in our children and pass them upwards
     *
     * @param {Array} nodes Mixed, some single nodes, some arrays of nodes.
     * @return {string}
     */
    concat: function concat(nodes) {
      var result = '';
      $.each(nodes, function (i, node) {
        // strings, integers, anything else
        result += node;
      });
      return result;
    },
    /**
     * Return escaped replacement of correct index, or string if
     * unavailable. Note that we expect the parsed parameter to be
     * zero-based. i.e. $1 should have become [ 0 ]. if the specified
     * parameter is not found return the same string (e.g. "$99" ->
     * parameter 98 -> not found -> return "$99" ) TODO throw error if
     * nodes.length > 1 ?
     *
     * @param {Array} nodes One element, integer, n >= 0
     * @param {Array} replacements for $1, $2, ... $n
     * @return {string} replacement
     */
    replace: function replace(nodes, replacements) {
      var index = parseInt(nodes[0], 10);
      if (index < replacements.length) {
        // replacement is not a string, don't touch!
        return replacements[index];
      } else {
        // index not found, fallback to displaying variable
        return '$' + (index + 1);
      }
    },
    /**
     * Transform parsed structure into pluralization n.b. The first node may
     * be a non-integer (for instance, a string representing an Arabic
     * number). So convert it back with the current language's
     * convertNumber.
     *
     * @param {Array} nodes List [ {String|Number}, {String}, {String} ... ]
     * @return {string} selected pluralized form according to current
     *  language.
     */
    plural: function plural(nodes) {
      var count = parseFloat(this.language.convertNumber(nodes[0], 10)),
        forms = nodes.slice(1);
      return forms.length ? this.language.convertPlural(count, forms) : '';
    },
    /**
     * Transform parsed structure into gender Usage
     * {{gender:gender|masculine|feminine|neutral}}.
     *
     * @param {Array} nodes List [ {String}, {String}, {String} , {String} ]
     * @return {string} selected gender form according to current language
     */
    gender: function gender(nodes) {
      var gender = nodes[0],
        forms = nodes.slice(1);
      return this.language.gender(gender, forms);
    },
    /**
     * Transform parsed structure into grammar conversion. Invoked by
     * putting {{grammar:form|word}} in a message
     *
     * @param {Array} nodes List [{Grammar case eg: genitive}, {String word}]
     * @return {string} selected grammatical form according to current
     *  language.
     */
    grammar: function grammar(nodes) {
      var form = nodes[0],
        word = nodes[1];
      return word && form && this.language.convertGrammar(word, form);
    }
  };
  $.extend($.i18n.parser.emitter, new MessageParserEmitter());
})(jQuery);
/*global pluralRuleParser */
(function ($) {
  'use strict';

  // jscs:disable
  var language = {
    // CLDR plural rules generated using
    // libs/CLDRPluralRuleParser/tools/PluralXML2JSON.html
    'pluralRules': {
      'af': {
        'one': 'n = 1'
      },
      'ak': {
        'one': 'n = 0..1'
      },
      'am': {
        'one': 'i = 0 or n = 1'
      },
      'ar': {
        'zero': 'n = 0',
        'one': 'n = 1',
        'two': 'n = 2',
        'few': 'n % 100 = 3..10',
        'many': 'n % 100 = 11..99'
      },
      'ars': {
        'zero': 'n = 0',
        'one': 'n = 1',
        'two': 'n = 2',
        'few': 'n % 100 = 3..10',
        'many': 'n % 100 = 11..99'
      },
      'as': {
        'one': 'i = 0 or n = 1'
      },
      'asa': {
        'one': 'n = 1'
      },
      'ast': {
        'one': 'i = 1 and v = 0'
      },
      'az': {
        'one': 'n = 1'
      },
      'be': {
        'one': 'n % 10 = 1 and n % 100 != 11',
        'few': 'n % 10 = 2..4 and n % 100 != 12..14',
        'many': 'n % 10 = 0 or n % 10 = 5..9 or n % 100 = 11..14'
      },
      'bem': {
        'one': 'n = 1'
      },
      'bez': {
        'one': 'n = 1'
      },
      'bg': {
        'one': 'n = 1'
      },
      'bh': {
        'one': 'n = 0..1'
      },
      'bm': {},
      'bn': {
        'one': 'i = 0 or n = 1'
      },
      'bo': {},
      'br': {
        'one': 'n % 10 = 1 and n % 100 != 11,71,91',
        'two': 'n % 10 = 2 and n % 100 != 12,72,92',
        'few': 'n % 10 = 3..4,9 and n % 100 != 10..19,70..79,90..99',
        'many': 'n != 0 and n % 1000000 = 0'
      },
      'brx': {
        'one': 'n = 1'
      },
      'bs': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
      },
      'ca': {
        'one': 'i = 1 and v = 0'
      },
      'ce': {
        'one': 'n = 1'
      },
      'cgg': {
        'one': 'n = 1'
      },
      'chr': {
        'one': 'n = 1'
      },
      'ckb': {
        'one': 'n = 1'
      },
      'cs': {
        'one': 'i = 1 and v = 0',
        'few': 'i = 2..4 and v = 0',
        'many': 'v != 0'
      },
      'cy': {
        'zero': 'n = 0',
        'one': 'n = 1',
        'two': 'n = 2',
        'few': 'n = 3',
        'many': 'n = 6'
      },
      'da': {
        'one': 'n = 1 or t != 0 and i = 0,1'
      },
      'de': {
        'one': 'i = 1 and v = 0'
      },
      'dsb': {
        'one': 'v = 0 and i % 100 = 1 or f % 100 = 1',
        'two': 'v = 0 and i % 100 = 2 or f % 100 = 2',
        'few': 'v = 0 and i % 100 = 3..4 or f % 100 = 3..4'
      },
      'dv': {
        'one': 'n = 1'
      },
      'dz': {},
      'ee': {
        'one': 'n = 1'
      },
      'el': {
        'one': 'n = 1'
      },
      'en': {
        'one': 'i = 1 and v = 0'
      },
      'eo': {
        'one': 'n = 1'
      },
      'es': {
        'one': 'n = 1'
      },
      'et': {
        'one': 'i = 1 and v = 0'
      },
      'eu': {
        'one': 'n = 1'
      },
      'fa': {
        'one': 'i = 0 or n = 1'
      },
      'ff': {
        'one': 'i = 0,1'
      },
      'fi': {
        'one': 'i = 1 and v = 0'
      },
      'fil': {
        'one': 'v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9'
      },
      'fo': {
        'one': 'n = 1'
      },
      'fr': {
        'one': 'i = 0,1'
      },
      'fur': {
        'one': 'n = 1'
      },
      'fy': {
        'one': 'i = 1 and v = 0'
      },
      'ga': {
        'one': 'n = 1',
        'two': 'n = 2',
        'few': 'n = 3..6',
        'many': 'n = 7..10'
      },
      'gd': {
        'one': 'n = 1,11',
        'two': 'n = 2,12',
        'few': 'n = 3..10,13..19'
      },
      'gl': {
        'one': 'i = 1 and v = 0'
      },
      'gsw': {
        'one': 'n = 1'
      },
      'gu': {
        'one': 'i = 0 or n = 1'
      },
      'guw': {
        'one': 'n = 0..1'
      },
      'gv': {
        'one': 'v = 0 and i % 10 = 1',
        'two': 'v = 0 and i % 10 = 2',
        'few': 'v = 0 and i % 100 = 0,20,40,60,80',
        'many': 'v != 0'
      },
      'ha': {
        'one': 'n = 1'
      },
      'haw': {
        'one': 'n = 1'
      },
      'he': {
        'one': 'i = 1 and v = 0',
        'two': 'i = 2 and v = 0',
        'many': 'v = 0 and n != 0..10 and n % 10 = 0'
      },
      'hi': {
        'one': 'i = 0 or n = 1'
      },
      'hr': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
      },
      'hsb': {
        'one': 'v = 0 and i % 100 = 1 or f % 100 = 1',
        'two': 'v = 0 and i % 100 = 2 or f % 100 = 2',
        'few': 'v = 0 and i % 100 = 3..4 or f % 100 = 3..4'
      },
      'hu': {
        'one': 'n = 1'
      },
      'hy': {
        'one': 'i = 0,1'
      },
      'id': {},
      'ig': {},
      'ii': {},
      'in': {},
      'is': {
        'one': 't = 0 and i % 10 = 1 and i % 100 != 11 or t != 0'
      },
      'it': {
        'one': 'i = 1 and v = 0'
      },
      'iu': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'iw': {
        'one': 'i = 1 and v = 0',
        'two': 'i = 2 and v = 0',
        'many': 'v = 0 and n != 0..10 and n % 10 = 0'
      },
      'ja': {},
      'jbo': {},
      'jgo': {
        'one': 'n = 1'
      },
      'ji': {
        'one': 'i = 1 and v = 0'
      },
      'jmc': {
        'one': 'n = 1'
      },
      'jv': {},
      'jw': {},
      'ka': {
        'one': 'n = 1'
      },
      'kab': {
        'one': 'i = 0,1'
      },
      'kaj': {
        'one': 'n = 1'
      },
      'kcg': {
        'one': 'n = 1'
      },
      'kde': {},
      'kea': {},
      'kk': {
        'one': 'n = 1'
      },
      'kkj': {
        'one': 'n = 1'
      },
      'kl': {
        'one': 'n = 1'
      },
      'km': {},
      'kn': {
        'one': 'i = 0 or n = 1'
      },
      'ko': {},
      'ks': {
        'one': 'n = 1'
      },
      'ksb': {
        'one': 'n = 1'
      },
      'ksh': {
        'zero': 'n = 0',
        'one': 'n = 1'
      },
      'ku': {
        'one': 'n = 1'
      },
      'kw': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'ky': {
        'one': 'n = 1'
      },
      'lag': {
        'zero': 'n = 0',
        'one': 'i = 0,1 and n != 0'
      },
      'lb': {
        'one': 'n = 1'
      },
      'lg': {
        'one': 'n = 1'
      },
      'lkt': {},
      'ln': {
        'one': 'n = 0..1'
      },
      'lo': {},
      'lt': {
        'one': 'n % 10 = 1 and n % 100 != 11..19',
        'few': 'n % 10 = 2..9 and n % 100 != 11..19',
        'many': 'f != 0'
      },
      'lv': {
        'zero': 'n % 10 = 0 or n % 100 = 11..19 or v = 2 and f % 100 = 11..19',
        'one': 'n % 10 = 1 and n % 100 != 11 or v = 2 and f % 10 = 1 and f % 100 != 11 or v != 2 and f % 10 = 1'
      },
      'mas': {
        'one': 'n = 1'
      },
      'mg': {
        'one': 'n = 0..1'
      },
      'mgo': {
        'one': 'n = 1'
      },
      'mk': {
        'one': 'v = 0 and i % 10 = 1 or f % 10 = 1'
      },
      'ml': {
        'one': 'n = 1'
      },
      'mn': {
        'one': 'n = 1'
      },
      'mo': {
        'one': 'i = 1 and v = 0',
        'few': 'v != 0 or n = 0 or n != 1 and n % 100 = 1..19'
      },
      'mr': {
        'one': 'i = 0 or n = 1'
      },
      'ms': {},
      'mt': {
        'one': 'n = 1',
        'few': 'n = 0 or n % 100 = 2..10',
        'many': 'n % 100 = 11..19'
      },
      'my': {},
      'nah': {
        'one': 'n = 1'
      },
      'naq': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'nb': {
        'one': 'n = 1'
      },
      'nd': {
        'one': 'n = 1'
      },
      'ne': {
        'one': 'n = 1'
      },
      'nl': {
        'one': 'i = 1 and v = 0'
      },
      'nn': {
        'one': 'n = 1'
      },
      'nnh': {
        'one': 'n = 1'
      },
      'no': {
        'one': 'n = 1'
      },
      'nqo': {},
      'nr': {
        'one': 'n = 1'
      },
      'nso': {
        'one': 'n = 0..1'
      },
      'ny': {
        'one': 'n = 1'
      },
      'nyn': {
        'one': 'n = 1'
      },
      'om': {
        'one': 'n = 1'
      },
      'or': {
        'one': 'n = 1'
      },
      'os': {
        'one': 'n = 1'
      },
      'pa': {
        'one': 'n = 0..1'
      },
      'pap': {
        'one': 'n = 1'
      },
      'pl': {
        'one': 'i = 1 and v = 0',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
        'many': 'v = 0 and i != 1 and i % 10 = 0..1 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 12..14'
      },
      'prg': {
        'zero': 'n % 10 = 0 or n % 100 = 11..19 or v = 2 and f % 100 = 11..19',
        'one': 'n % 10 = 1 and n % 100 != 11 or v = 2 and f % 10 = 1 and f % 100 != 11 or v != 2 and f % 10 = 1'
      },
      'ps': {
        'one': 'n = 1'
      },
      'pt': {
        'one': 'n = 0..2 and n != 2'
      },
      'pt-PT': {
        'one': 'n = 1 and v = 0'
      },
      'rm': {
        'one': 'n = 1'
      },
      'ro': {
        'one': 'i = 1 and v = 0',
        'few': 'v != 0 or n = 0 or n != 1 and n % 100 = 1..19'
      },
      'rof': {
        'one': 'n = 1'
      },
      'root': {},
      'ru': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
        'many': 'v = 0 and i % 10 = 0 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 11..14'
      },
      'rwk': {
        'one': 'n = 1'
      },
      'sah': {},
      'saq': {
        'one': 'n = 1'
      },
      'sdh': {
        'one': 'n = 1'
      },
      'se': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'seh': {
        'one': 'n = 1'
      },
      'ses': {},
      'sg': {},
      'sh': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
      },
      'shi': {
        'one': 'i = 0 or n = 1',
        'few': 'n = 2..10'
      },
      'si': {
        'one': 'n = 0,1 or i = 0 and f = 1'
      },
      'sk': {
        'one': 'i = 1 and v = 0',
        'few': 'i = 2..4 and v = 0',
        'many': 'v != 0'
      },
      'sl': {
        'one': 'v = 0 and i % 100 = 1',
        'two': 'v = 0 and i % 100 = 2',
        'few': 'v = 0 and i % 100 = 3..4 or v != 0'
      },
      'sma': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'smi': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'smj': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'smn': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'sms': {
        'one': 'n = 1',
        'two': 'n = 2'
      },
      'sn': {
        'one': 'n = 1'
      },
      'so': {
        'one': 'n = 1'
      },
      'sq': {
        'one': 'n = 1'
      },
      'sr': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14 or f % 10 = 2..4 and f % 100 != 12..14'
      },
      'ss': {
        'one': 'n = 1'
      },
      'ssy': {
        'one': 'n = 1'
      },
      'st': {
        'one': 'n = 1'
      },
      'sv': {
        'one': 'i = 1 and v = 0'
      },
      'sw': {
        'one': 'i = 1 and v = 0'
      },
      'syr': {
        'one': 'n = 1'
      },
      'ta': {
        'one': 'n = 1'
      },
      'te': {
        'one': 'n = 1'
      },
      'teo': {
        'one': 'n = 1'
      },
      'th': {},
      'ti': {
        'one': 'n = 0..1'
      },
      'tig': {
        'one': 'n = 1'
      },
      'tk': {
        'one': 'n = 1'
      },
      'tl': {
        'one': 'v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9'
      },
      'tn': {
        'one': 'n = 1'
      },
      'to': {},
      'tr': {
        'one': 'n = 1'
      },
      'ts': {
        'one': 'n = 1'
      },
      'tzm': {
        'one': 'n = 0..1 or n = 11..99'
      },
      'ug': {
        'one': 'n = 1'
      },
      'uk': {
        'one': 'v = 0 and i % 10 = 1 and i % 100 != 11',
        'few': 'v = 0 and i % 10 = 2..4 and i % 100 != 12..14',
        'many': 'v = 0 and i % 10 = 0 or v = 0 and i % 10 = 5..9 or v = 0 and i % 100 = 11..14'
      },
      'ur': {
        'one': 'i = 1 and v = 0'
      },
      'uz': {
        'one': 'n = 1'
      },
      've': {
        'one': 'n = 1'
      },
      'vi': {},
      'vo': {
        'one': 'n = 1'
      },
      'vun': {
        'one': 'n = 1'
      },
      'wa': {
        'one': 'n = 0..1'
      },
      'wae': {
        'one': 'n = 1'
      },
      'wo': {},
      'xh': {
        'one': 'n = 1'
      },
      'xog': {
        'one': 'n = 1'
      },
      'yi': {
        'one': 'i = 1 and v = 0'
      },
      'yo': {},
      'yue': {},
      'zh': {},
      'zu': {
        'one': 'i = 0 or n = 1'
      }
    },
    // jscs:enable

    /**
     * Plural form transformations, needed for some languages.
     *
     * @param {integer} count
     *            Non-localized quantifier
     * @param {Array} forms
     *            List of plural forms
     * @return {string} Correct form for quantifier in this language
     */
    convertPlural: function convertPlural(count, forms) {
      var pluralRules,
        pluralFormIndex,
        index,
        explicitPluralPattern = new RegExp('\\d+=', 'i'),
        formCount,
        form;
      if (!forms || forms.length === 0) {
        return '';
      }

      // Handle for Explicit 0= & 1= values
      for (index = 0; index < forms.length; index++) {
        form = forms[index];
        if (explicitPluralPattern.test(form)) {
          formCount = parseInt(form.slice(0, form.indexOf('=')), 10);
          if (formCount === count) {
            return form.slice(form.indexOf('=') + 1);
          }
          forms[index] = undefined;
        }
      }
      forms = $.map(forms, function (form) {
        if (form !== undefined) {
          return form;
        }
      });
      pluralRules = this.pluralRules[$.i18n().locale];
      if (!pluralRules) {
        // default fallback.
        return count === 1 ? forms[0] : forms[1];
      }
      pluralFormIndex = this.getPluralForm(count, pluralRules);
      pluralFormIndex = Math.min(pluralFormIndex, forms.length - 1);
      return forms[pluralFormIndex];
    },
    /**
     * For the number, get the plural for index
     *
     * @param {integer} number
     * @param {Object} pluralRules
     * @return {integer} plural form index
     */
    getPluralForm: function getPluralForm(number, pluralRules) {
      var i,
        pluralForms = ['zero', 'one', 'two', 'few', 'many', 'other'],
        pluralFormIndex = 0;
      for (i = 0; i < pluralForms.length; i++) {
        if (pluralRules[pluralForms[i]]) {
          if (pluralRuleParser(pluralRules[pluralForms[i]], number)) {
            return pluralFormIndex;
          }
          pluralFormIndex++;
        }
      }
      return pluralFormIndex;
    },
    /**
     * Converts a number using digitTransformTable.
     *
     * @param {number} num Value to be converted
     * @param {boolean} integer Convert the return value to an integer
     */
    convertNumber: function convertNumber(num, integer) {
      var tmp, item, i, transformTable, numberString, convertedNumber;

      // Set the target Transform table:
      transformTable = this.digitTransformTable($.i18n().locale);
      numberString = String(num);
      convertedNumber = '';
      if (!transformTable) {
        return num;
      }

      // Check if the restore to Latin number flag is set:
      if (integer) {
        if (parseFloat(num, 10) === num) {
          return num;
        }
        tmp = [];
        for (item in transformTable) {
          tmp[transformTable[item]] = item;
        }
        transformTable = tmp;
      }
      for (i = 0; i < numberString.length; i++) {
        if (transformTable[numberString[i]]) {
          convertedNumber += transformTable[numberString[i]];
        } else {
          convertedNumber += numberString[i];
        }
      }
      return integer ? parseFloat(convertedNumber, 10) : convertedNumber;
    },
    /**
     * Grammatical transformations, needed for inflected languages.
     * Invoked by putting {{grammar:form|word}} in a message.
     * Override this method for languages that need special grammar rules
     * applied dynamically.
     *
     * @param {string} word
     * @param {string} form
     * @return {string}
     */
    convertGrammar: function convertGrammar(word, form) {
      /*jshint unused: false */
      return word;
    },
    /**
     * Provides an alternative text depending on specified gender. Usage
     * {{gender:[gender|user object]|masculine|feminine|neutral}}. If second
     * or third parameter are not specified, masculine is used.
     *
     * These details may be overriden per language.
     *
     * @param {string} gender
     *      male, female, or anything else for neutral.
     * @param {Array} forms
     *      List of gender forms
     *
     * @return {string}
     */
    gender: function gender(_gender, forms) {
      if (!forms || forms.length === 0) {
        return '';
      }
      while (forms.length < 2) {
        forms.push(forms[forms.length - 1]);
      }
      if (_gender === 'male') {
        return forms[0];
      }
      if (_gender === 'female') {
        return forms[1];
      }
      return forms.length === 3 ? forms[2] : forms[0];
    },
    /**
     * Get the digit transform table for the given language
     * See http://cldr.unicode.org/translation/numbering-systems
     *
     * @param {string} language
     * @return {Array|boolean} List of digits in the passed language or false
     * representation, or boolean false if there is no information.
     */
    digitTransformTable: function digitTransformTable(language) {
      var tables = {
        ar: '٠١٢٣٤٥٦٧٨٩',
        fa: '۰۱۲۳۴۵۶۷۸۹',
        ml: '൦൧൨൩൪൫൬൭൮൯',
        kn: '೦೧೨೩೪೫೬೭೮೯',
        lo: '໐໑໒໓໔໕໖໗໘໙',
        or: '୦୧୨୩୪୫୬୭୮୯',
        kh: '០១២៣៤៥៦៧៨៩',
        pa: '੦੧੨੩੪੫੬੭੮੯',
        gu: '૦૧૨૩૪૫૬૭૮૯',
        hi: '०१२३४५६७८९',
        my: '၀၁၂၃၄၅၆၇၈၉',
        ta: '௦௧௨௩௪௫௬௭௮௯',
        te: '౦౧౨౩౪౫౬౭౮౯',
        th: '๐๑๒๓๔๕๖๗๘๙',
        // FIXME use iso 639 codes
        bo: '༠༡༢༣༤༥༦༧༨༩' // FIXME use iso 639 codes
      };

      if (!tables[language]) {
        return false;
      }
      return tables[language].split('');
    }
  };
  $.extend($.i18n.languages, {
    'default': language
  });
})(jQuery);
/**
 * cldrpluralparser.js
 * A parser engine for CLDR plural rules.
 *
 * Copyright 2012-2014 Santhosh Thottingal and other contributors
 * Released under the MIT license
 * http://opensource.org/licenses/MIT
 *
 * @version 0.1.0
 * @source https://github.com/santhoshtr/CLDRPluralRuleParser
 * @author Santhosh Thottingal <santhosh.thottingal@gmail.com>
 * @author Timo Tijhof
 * @author Amir Aharoni
 */

/**
 * Evaluates a plural rule in CLDR syntax for a number
 * @param {string} rule
 * @param {integer} number
 * @return {boolean} true if evaluation passed, false if evaluation failed.
 */

// UMD returnExports https://github.com/umdjs/umd/blob/master/returnExports.js
(function (root, factory) {
  if (true) {
    // AMD. Register as an anonymous module.
    !(__WEBPACK_AMD_DEFINE_FACTORY__ = (factory),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
  } else {}
})(this, function () {
  window.pluralRuleParser = function (rule, number) {
    'use strict';

    /*
    Syntax: see http://unicode.org/reports/tr35/#Language_Plural_Rules
    -----------------------------------------------------------------
    condition     = and_condition ('or' and_condition)*
    	('@integer' samples)?
    	('@decimal' samples)?
    and_condition = relation ('and' relation)*
    relation      = is_relation | in_relation | within_relation
    is_relation   = expr 'is' ('not')? value
    in_relation   = expr (('not')? 'in' | '=' | '!=') range_list
    within_relation = expr ('not')? 'within' range_list
    expr          = operand (('mod' | '%') value)?
    operand       = 'n' | 'i' | 'f' | 't' | 'v' | 'w'
    range_list    = (range | value) (',' range_list)*
    value         = digit+
    digit         = 0|1|2|3|4|5|6|7|8|9
    range         = value'..'value
    samples       = sampleRange (',' sampleRange)* (',' ('…'|'...'))?
    sampleRange   = decimalValue '~' decimalValue
    decimalValue  = value ('.' value)?
    */

    // We don't evaluate the samples section of the rule. Ignore it.
    rule = rule.split('@')[0].replace(/^\s*/, '').replace(/\s*$/, '');
    if (!rule.length) {
      // Empty rule or 'other' rule.
      return true;
    }

    // Indicates the current position in the rule as we parse through it.
    // Shared among all parsing functions below.
    var pos = 0,
      operand,
      expression,
      relation,
      result,
      whitespace = makeRegexParser(/^\s+/),
      value = makeRegexParser(/^\d+/),
      _n_ = makeStringParser('n'),
      _i_ = makeStringParser('i'),
      _f_ = makeStringParser('f'),
      _t_ = makeStringParser('t'),
      _v_ = makeStringParser('v'),
      _w_ = makeStringParser('w'),
      _is_ = makeStringParser('is'),
      _isnot_ = makeStringParser('is not'),
      _isnot_sign_ = makeStringParser('!='),
      _equal_ = makeStringParser('='),
      _mod_ = makeStringParser('mod'),
      _percent_ = makeStringParser('%'),
      _not_ = makeStringParser('not'),
      _in_ = makeStringParser('in'),
      _within_ = makeStringParser('within'),
      _range_ = makeStringParser('..'),
      _comma_ = makeStringParser(','),
      _or_ = makeStringParser('or'),
      _and_ = makeStringParser('and');
    function debug() {
      // console.log.apply(console, arguments);
    }
    debug('pluralRuleParser', rule, number);

    // Try parsers until one works, if none work return null
    function choice(parserSyntax) {
      return function () {
        var i, result;
        for (i = 0; i < parserSyntax.length; i++) {
          result = parserSyntax[i]();
          if (result !== null) {
            return result;
          }
        }
        return null;
      };
    }

    // Try several parserSyntax-es in a row.
    // All must succeed; otherwise, return null.
    // This is the only eager one.
    function sequence(parserSyntax) {
      var i,
        parserRes,
        originalPos = pos,
        result = [];
      for (i = 0; i < parserSyntax.length; i++) {
        parserRes = parserSyntax[i]();
        if (parserRes === null) {
          pos = originalPos;
          return null;
        }
        result.push(parserRes);
      }
      return result;
    }

    // Run the same parser over and over until it fails.
    // Must succeed a minimum of n times; otherwise, return null.
    function nOrMore(n, p) {
      return function () {
        var originalPos = pos,
          result = [],
          parsed = p();
        while (parsed !== null) {
          result.push(parsed);
          parsed = p();
        }
        if (result.length < n) {
          pos = originalPos;
          return null;
        }
        return result;
      };
    }

    // Helpers - just make parserSyntax out of simpler JS builtin types
    function makeStringParser(s) {
      var len = s.length;
      return function () {
        var result = null;
        if (rule.substr(pos, len) === s) {
          result = s;
          pos += len;
        }
        return result;
      };
    }
    function makeRegexParser(regex) {
      return function () {
        var matches = rule.substr(pos).match(regex);
        if (matches === null) {
          return null;
        }
        pos += matches[0].length;
        return matches[0];
      };
    }

    /**
     * Integer digits of n.
     */
    function i() {
      var result = _i_();
      if (result === null) {
        debug(' -- failed i', parseInt(number, 10));
        return result;
      }
      result = parseInt(number, 10);
      debug(' -- passed i ', result);
      return result;
    }

    /**
     * Absolute value of the source number (integer and decimals).
     */
    function n() {
      var result = _n_();
      if (result === null) {
        debug(' -- failed n ', number);
        return result;
      }
      result = parseFloat(number, 10);
      debug(' -- passed n ', result);
      return result;
    }

    /**
     * Visible fractional digits in n, with trailing zeros.
     */
    function f() {
      var result = _f_();
      if (result === null) {
        debug(' -- failed f ', number);
        return result;
      }
      result = (number + '.').split('.')[1] || 0;
      debug(' -- passed f ', result);
      return result;
    }

    /**
     * Visible fractional digits in n, without trailing zeros.
     */
    function t() {
      var result = _t_();
      if (result === null) {
        debug(' -- failed t ', number);
        return result;
      }
      result = (number + '.').split('.')[1].replace(/0$/, '') || 0;
      debug(' -- passed t ', result);
      return result;
    }

    /**
     * Number of visible fraction digits in n, with trailing zeros.
     */
    function v() {
      var result = _v_();
      if (result === null) {
        debug(' -- failed v ', number);
        return result;
      }
      result = (number + '.').split('.')[1].length || 0;
      debug(' -- passed v ', result);
      return result;
    }

    /**
     * Number of visible fraction digits in n, without trailing zeros.
     */
    function w() {
      var result = _w_();
      if (result === null) {
        debug(' -- failed w ', number);
        return result;
      }
      result = (number + '.').split('.')[1].replace(/0$/, '').length || 0;
      debug(' -- passed w ', result);
      return result;
    }

    // operand       = 'n' | 'i' | 'f' | 't' | 'v' | 'w'
    operand = choice([n, i, f, t, v, w]);

    // expr          = operand (('mod' | '%') value)?
    expression = choice([mod, operand]);
    function mod() {
      var result = sequence([operand, whitespace, choice([_mod_, _percent_]), whitespace, value]);
      if (result === null) {
        debug(' -- failed mod');
        return null;
      }
      debug(' -- passed ' + parseInt(result[0], 10) + ' ' + result[2] + ' ' + parseInt(result[4], 10));
      return parseInt(result[0], 10) % parseInt(result[4], 10);
    }
    function not() {
      var result = sequence([whitespace, _not_]);
      if (result === null) {
        debug(' -- failed not');
        return null;
      }
      return result[1];
    }

    // is_relation   = expr 'is' ('not')? value
    function is() {
      var result = sequence([expression, whitespace, choice([_is_]), whitespace, value]);
      if (result !== null) {
        debug(' -- passed is : ' + result[0] + ' == ' + parseInt(result[4], 10));
        return result[0] === parseInt(result[4], 10);
      }
      debug(' -- failed is');
      return null;
    }

    // is_relation   = expr 'is' ('not')? value
    function isnot() {
      var result = sequence([expression, whitespace, choice([_isnot_, _isnot_sign_]), whitespace, value]);
      if (result !== null) {
        debug(' -- passed isnot: ' + result[0] + ' != ' + parseInt(result[4], 10));
        return result[0] !== parseInt(result[4], 10);
      }
      debug(' -- failed isnot');
      return null;
    }
    function not_in() {
      var i,
        range_list,
        result = sequence([expression, whitespace, _isnot_sign_, whitespace, rangeList]);
      if (result !== null) {
        debug(' -- passed not_in: ' + result[0] + ' != ' + result[4]);
        range_list = result[4];
        for (i = 0; i < range_list.length; i++) {
          if (parseInt(range_list[i], 10) === parseInt(result[0], 10)) {
            return false;
          }
        }
        return true;
      }
      debug(' -- failed not_in');
      return null;
    }

    // range_list    = (range | value) (',' range_list)*
    function rangeList() {
      var result = sequence([choice([range, value]), nOrMore(0, rangeTail)]),
        resultList = [];
      if (result !== null) {
        resultList = resultList.concat(result[0]);
        if (result[1][0]) {
          resultList = resultList.concat(result[1][0]);
        }
        return resultList;
      }
      debug(' -- failed rangeList');
      return null;
    }
    function rangeTail() {
      // ',' range_list
      var result = sequence([_comma_, rangeList]);
      if (result !== null) {
        return result[1];
      }
      debug(' -- failed rangeTail');
      return null;
    }

    // range         = value'..'value
    function range() {
      var i,
        array,
        left,
        right,
        result = sequence([value, _range_, value]);
      if (result !== null) {
        debug(' -- passed range');
        array = [];
        left = parseInt(result[0], 10);
        right = parseInt(result[2], 10);
        for (i = left; i <= right; i++) {
          array.push(i);
        }
        return array;
      }
      debug(' -- failed range');
      return null;
    }
    function _in() {
      var result, range_list, i;

      // in_relation   = expr ('not')? 'in' range_list
      result = sequence([expression, nOrMore(0, not), whitespace, choice([_in_, _equal_]), whitespace, rangeList]);
      if (result !== null) {
        debug(' -- passed _in:' + result);
        range_list = result[5];
        for (i = 0; i < range_list.length; i++) {
          if (parseInt(range_list[i], 10) === parseInt(result[0], 10)) {
            return result[1][0] !== 'not';
          }
        }
        return result[1][0] === 'not';
      }
      debug(' -- failed _in ');
      return null;
    }

    /**
     * The difference between "in" and "within" is that
     * "in" only includes integers in the specified range,
     * while "within" includes all values.
     */
    function within() {
      var range_list, result;

      // within_relation = expr ('not')? 'within' range_list
      result = sequence([expression, nOrMore(0, not), whitespace, _within_, whitespace, rangeList]);
      if (result !== null) {
        debug(' -- passed within');
        range_list = result[5];
        if (result[0] >= parseInt(range_list[0], 10) && result[0] < parseInt(range_list[range_list.length - 1], 10)) {
          return result[1][0] !== 'not';
        }
        return result[1][0] === 'not';
      }
      debug(' -- failed within ');
      return null;
    }

    // relation      = is_relation | in_relation | within_relation
    relation = choice([is, not_in, isnot, _in, within]);

    // and_condition = relation ('and' relation)*
    function and() {
      var i,
        result = sequence([relation, nOrMore(0, andTail)]);
      if (result) {
        if (!result[0]) {
          return false;
        }
        for (i = 0; i < result[1].length; i++) {
          if (!result[1][i]) {
            return false;
          }
        }
        return true;
      }
      debug(' -- failed and');
      return null;
    }

    // ('and' relation)*
    function andTail() {
      var result = sequence([whitespace, _and_, whitespace, relation]);
      if (result !== null) {
        debug(' -- passed andTail' + result);
        return result[3];
      }
      debug(' -- failed andTail');
      return null;
    }
    //  ('or' and_condition)*
    function orTail() {
      var result = sequence([whitespace, _or_, whitespace, and]);
      if (result !== null) {
        debug(' -- passed orTail: ' + result[3]);
        return result[3];
      }
      debug(' -- failed orTail');
      return null;
    }

    // condition     = and_condition ('or' and_condition)*
    function condition() {
      var i,
        result = sequence([and, nOrMore(0, orTail)]);
      if (result) {
        for (i = 0; i < result[1].length; i++) {
          if (result[1][i]) {
            return true;
          }
        }
        return result[0];
      }
      return false;
    }
    result = condition();

    /**
     * For success, the pos must have gotten to the end of the rule
     * and returned a non-null.
     * n.b. This is part of language infrastructure,
     * so we do not throw an internationalizable message.
     */
    if (result === null) {
      throw new Error('Parse error at position ' + pos.toString() + ' for rule: ' + rule);
    }
    if (pos !== rule.length) {
      debug('Warning: Rule not parsed completely. Parser stopped at ' + rule.substr(0, pos) + ' for rule: ' + rule);
    }
    return result;
  };
  return pluralRuleParser;
});

/***/ }),

/***/ "./assets/css/application.scss":
/*!*************************************!*\
  !*** ./assets/css/application.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/articleinfo.scss":
/*!*************************************!*\
  !*** ./assets/css/articleinfo.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/autoedits.scss":
/*!***********************************!*\
  !*** ./assets/css/autoedits.scss ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/blame.scss":
/*!*******************************!*\
  !*** ./assets/css/blame.scss ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/categoryedits.scss":
/*!***************************************!*\
  !*** ./assets/css/categoryedits.scss ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/editcounter.scss":
/*!*************************************!*\
  !*** ./assets/css/editcounter.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/home.scss":
/*!******************************!*\
  !*** ./assets/css/home.scss ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/meta.scss":
/*!******************************!*\
  !*** ./assets/css/meta.scss ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/pages.scss":
/*!*******************************!*\
  !*** ./assets/css/pages.scss ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/responsive.scss":
/*!************************************!*\
  !*** ./assets/css/responsive.scss ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/topedits.scss":
/*!**********************************!*\
  !*** ./assets/css/topedits.scss ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./node_modules/moment/locale sync recursive ^\\.\\/.*$":
/*!***************************************************!*\
  !*** ./node_modules/moment/locale/ sync ^\.\/.*$ ***!
  \***************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var map = {
	"./af": "./node_modules/moment/locale/af.js",
	"./af.js": "./node_modules/moment/locale/af.js",
	"./ar": "./node_modules/moment/locale/ar.js",
	"./ar-dz": "./node_modules/moment/locale/ar-dz.js",
	"./ar-dz.js": "./node_modules/moment/locale/ar-dz.js",
	"./ar-kw": "./node_modules/moment/locale/ar-kw.js",
	"./ar-kw.js": "./node_modules/moment/locale/ar-kw.js",
	"./ar-ly": "./node_modules/moment/locale/ar-ly.js",
	"./ar-ly.js": "./node_modules/moment/locale/ar-ly.js",
	"./ar-ma": "./node_modules/moment/locale/ar-ma.js",
	"./ar-ma.js": "./node_modules/moment/locale/ar-ma.js",
	"./ar-sa": "./node_modules/moment/locale/ar-sa.js",
	"./ar-sa.js": "./node_modules/moment/locale/ar-sa.js",
	"./ar-tn": "./node_modules/moment/locale/ar-tn.js",
	"./ar-tn.js": "./node_modules/moment/locale/ar-tn.js",
	"./ar.js": "./node_modules/moment/locale/ar.js",
	"./az": "./node_modules/moment/locale/az.js",
	"./az.js": "./node_modules/moment/locale/az.js",
	"./be": "./node_modules/moment/locale/be.js",
	"./be.js": "./node_modules/moment/locale/be.js",
	"./bg": "./node_modules/moment/locale/bg.js",
	"./bg.js": "./node_modules/moment/locale/bg.js",
	"./bm": "./node_modules/moment/locale/bm.js",
	"./bm.js": "./node_modules/moment/locale/bm.js",
	"./bn": "./node_modules/moment/locale/bn.js",
	"./bn-bd": "./node_modules/moment/locale/bn-bd.js",
	"./bn-bd.js": "./node_modules/moment/locale/bn-bd.js",
	"./bn.js": "./node_modules/moment/locale/bn.js",
	"./bo": "./node_modules/moment/locale/bo.js",
	"./bo.js": "./node_modules/moment/locale/bo.js",
	"./br": "./node_modules/moment/locale/br.js",
	"./br.js": "./node_modules/moment/locale/br.js",
	"./bs": "./node_modules/moment/locale/bs.js",
	"./bs.js": "./node_modules/moment/locale/bs.js",
	"./ca": "./node_modules/moment/locale/ca.js",
	"./ca.js": "./node_modules/moment/locale/ca.js",
	"./cs": "./node_modules/moment/locale/cs.js",
	"./cs.js": "./node_modules/moment/locale/cs.js",
	"./cv": "./node_modules/moment/locale/cv.js",
	"./cv.js": "./node_modules/moment/locale/cv.js",
	"./cy": "./node_modules/moment/locale/cy.js",
	"./cy.js": "./node_modules/moment/locale/cy.js",
	"./da": "./node_modules/moment/locale/da.js",
	"./da.js": "./node_modules/moment/locale/da.js",
	"./de": "./node_modules/moment/locale/de.js",
	"./de-at": "./node_modules/moment/locale/de-at.js",
	"./de-at.js": "./node_modules/moment/locale/de-at.js",
	"./de-ch": "./node_modules/moment/locale/de-ch.js",
	"./de-ch.js": "./node_modules/moment/locale/de-ch.js",
	"./de.js": "./node_modules/moment/locale/de.js",
	"./dv": "./node_modules/moment/locale/dv.js",
	"./dv.js": "./node_modules/moment/locale/dv.js",
	"./el": "./node_modules/moment/locale/el.js",
	"./el.js": "./node_modules/moment/locale/el.js",
	"./en-au": "./node_modules/moment/locale/en-au.js",
	"./en-au.js": "./node_modules/moment/locale/en-au.js",
	"./en-ca": "./node_modules/moment/locale/en-ca.js",
	"./en-ca.js": "./node_modules/moment/locale/en-ca.js",
	"./en-gb": "./node_modules/moment/locale/en-gb.js",
	"./en-gb.js": "./node_modules/moment/locale/en-gb.js",
	"./en-ie": "./node_modules/moment/locale/en-ie.js",
	"./en-ie.js": "./node_modules/moment/locale/en-ie.js",
	"./en-il": "./node_modules/moment/locale/en-il.js",
	"./en-il.js": "./node_modules/moment/locale/en-il.js",
	"./en-in": "./node_modules/moment/locale/en-in.js",
	"./en-in.js": "./node_modules/moment/locale/en-in.js",
	"./en-nz": "./node_modules/moment/locale/en-nz.js",
	"./en-nz.js": "./node_modules/moment/locale/en-nz.js",
	"./en-sg": "./node_modules/moment/locale/en-sg.js",
	"./en-sg.js": "./node_modules/moment/locale/en-sg.js",
	"./eo": "./node_modules/moment/locale/eo.js",
	"./eo.js": "./node_modules/moment/locale/eo.js",
	"./es": "./node_modules/moment/locale/es.js",
	"./es-do": "./node_modules/moment/locale/es-do.js",
	"./es-do.js": "./node_modules/moment/locale/es-do.js",
	"./es-mx": "./node_modules/moment/locale/es-mx.js",
	"./es-mx.js": "./node_modules/moment/locale/es-mx.js",
	"./es-us": "./node_modules/moment/locale/es-us.js",
	"./es-us.js": "./node_modules/moment/locale/es-us.js",
	"./es.js": "./node_modules/moment/locale/es.js",
	"./et": "./node_modules/moment/locale/et.js",
	"./et.js": "./node_modules/moment/locale/et.js",
	"./eu": "./node_modules/moment/locale/eu.js",
	"./eu.js": "./node_modules/moment/locale/eu.js",
	"./fa": "./node_modules/moment/locale/fa.js",
	"./fa.js": "./node_modules/moment/locale/fa.js",
	"./fi": "./node_modules/moment/locale/fi.js",
	"./fi.js": "./node_modules/moment/locale/fi.js",
	"./fil": "./node_modules/moment/locale/fil.js",
	"./fil.js": "./node_modules/moment/locale/fil.js",
	"./fo": "./node_modules/moment/locale/fo.js",
	"./fo.js": "./node_modules/moment/locale/fo.js",
	"./fr": "./node_modules/moment/locale/fr.js",
	"./fr-ca": "./node_modules/moment/locale/fr-ca.js",
	"./fr-ca.js": "./node_modules/moment/locale/fr-ca.js",
	"./fr-ch": "./node_modules/moment/locale/fr-ch.js",
	"./fr-ch.js": "./node_modules/moment/locale/fr-ch.js",
	"./fr.js": "./node_modules/moment/locale/fr.js",
	"./fy": "./node_modules/moment/locale/fy.js",
	"./fy.js": "./node_modules/moment/locale/fy.js",
	"./ga": "./node_modules/moment/locale/ga.js",
	"./ga.js": "./node_modules/moment/locale/ga.js",
	"./gd": "./node_modules/moment/locale/gd.js",
	"./gd.js": "./node_modules/moment/locale/gd.js",
	"./gl": "./node_modules/moment/locale/gl.js",
	"./gl.js": "./node_modules/moment/locale/gl.js",
	"./gom-deva": "./node_modules/moment/locale/gom-deva.js",
	"./gom-deva.js": "./node_modules/moment/locale/gom-deva.js",
	"./gom-latn": "./node_modules/moment/locale/gom-latn.js",
	"./gom-latn.js": "./node_modules/moment/locale/gom-latn.js",
	"./gu": "./node_modules/moment/locale/gu.js",
	"./gu.js": "./node_modules/moment/locale/gu.js",
	"./he": "./node_modules/moment/locale/he.js",
	"./he.js": "./node_modules/moment/locale/he.js",
	"./hi": "./node_modules/moment/locale/hi.js",
	"./hi.js": "./node_modules/moment/locale/hi.js",
	"./hr": "./node_modules/moment/locale/hr.js",
	"./hr.js": "./node_modules/moment/locale/hr.js",
	"./hu": "./node_modules/moment/locale/hu.js",
	"./hu.js": "./node_modules/moment/locale/hu.js",
	"./hy-am": "./node_modules/moment/locale/hy-am.js",
	"./hy-am.js": "./node_modules/moment/locale/hy-am.js",
	"./id": "./node_modules/moment/locale/id.js",
	"./id.js": "./node_modules/moment/locale/id.js",
	"./is": "./node_modules/moment/locale/is.js",
	"./is.js": "./node_modules/moment/locale/is.js",
	"./it": "./node_modules/moment/locale/it.js",
	"./it-ch": "./node_modules/moment/locale/it-ch.js",
	"./it-ch.js": "./node_modules/moment/locale/it-ch.js",
	"./it.js": "./node_modules/moment/locale/it.js",
	"./ja": "./node_modules/moment/locale/ja.js",
	"./ja.js": "./node_modules/moment/locale/ja.js",
	"./jv": "./node_modules/moment/locale/jv.js",
	"./jv.js": "./node_modules/moment/locale/jv.js",
	"./ka": "./node_modules/moment/locale/ka.js",
	"./ka.js": "./node_modules/moment/locale/ka.js",
	"./kk": "./node_modules/moment/locale/kk.js",
	"./kk.js": "./node_modules/moment/locale/kk.js",
	"./km": "./node_modules/moment/locale/km.js",
	"./km.js": "./node_modules/moment/locale/km.js",
	"./kn": "./node_modules/moment/locale/kn.js",
	"./kn.js": "./node_modules/moment/locale/kn.js",
	"./ko": "./node_modules/moment/locale/ko.js",
	"./ko.js": "./node_modules/moment/locale/ko.js",
	"./ku": "./node_modules/moment/locale/ku.js",
	"./ku.js": "./node_modules/moment/locale/ku.js",
	"./ky": "./node_modules/moment/locale/ky.js",
	"./ky.js": "./node_modules/moment/locale/ky.js",
	"./lb": "./node_modules/moment/locale/lb.js",
	"./lb.js": "./node_modules/moment/locale/lb.js",
	"./lo": "./node_modules/moment/locale/lo.js",
	"./lo.js": "./node_modules/moment/locale/lo.js",
	"./lt": "./node_modules/moment/locale/lt.js",
	"./lt.js": "./node_modules/moment/locale/lt.js",
	"./lv": "./node_modules/moment/locale/lv.js",
	"./lv.js": "./node_modules/moment/locale/lv.js",
	"./me": "./node_modules/moment/locale/me.js",
	"./me.js": "./node_modules/moment/locale/me.js",
	"./mi": "./node_modules/moment/locale/mi.js",
	"./mi.js": "./node_modules/moment/locale/mi.js",
	"./mk": "./node_modules/moment/locale/mk.js",
	"./mk.js": "./node_modules/moment/locale/mk.js",
	"./ml": "./node_modules/moment/locale/ml.js",
	"./ml.js": "./node_modules/moment/locale/ml.js",
	"./mn": "./node_modules/moment/locale/mn.js",
	"./mn.js": "./node_modules/moment/locale/mn.js",
	"./mr": "./node_modules/moment/locale/mr.js",
	"./mr.js": "./node_modules/moment/locale/mr.js",
	"./ms": "./node_modules/moment/locale/ms.js",
	"./ms-my": "./node_modules/moment/locale/ms-my.js",
	"./ms-my.js": "./node_modules/moment/locale/ms-my.js",
	"./ms.js": "./node_modules/moment/locale/ms.js",
	"./mt": "./node_modules/moment/locale/mt.js",
	"./mt.js": "./node_modules/moment/locale/mt.js",
	"./my": "./node_modules/moment/locale/my.js",
	"./my.js": "./node_modules/moment/locale/my.js",
	"./nb": "./node_modules/moment/locale/nb.js",
	"./nb.js": "./node_modules/moment/locale/nb.js",
	"./ne": "./node_modules/moment/locale/ne.js",
	"./ne.js": "./node_modules/moment/locale/ne.js",
	"./nl": "./node_modules/moment/locale/nl.js",
	"./nl-be": "./node_modules/moment/locale/nl-be.js",
	"./nl-be.js": "./node_modules/moment/locale/nl-be.js",
	"./nl.js": "./node_modules/moment/locale/nl.js",
	"./nn": "./node_modules/moment/locale/nn.js",
	"./nn.js": "./node_modules/moment/locale/nn.js",
	"./oc-lnc": "./node_modules/moment/locale/oc-lnc.js",
	"./oc-lnc.js": "./node_modules/moment/locale/oc-lnc.js",
	"./pa-in": "./node_modules/moment/locale/pa-in.js",
	"./pa-in.js": "./node_modules/moment/locale/pa-in.js",
	"./pl": "./node_modules/moment/locale/pl.js",
	"./pl.js": "./node_modules/moment/locale/pl.js",
	"./pt": "./node_modules/moment/locale/pt.js",
	"./pt-br": "./node_modules/moment/locale/pt-br.js",
	"./pt-br.js": "./node_modules/moment/locale/pt-br.js",
	"./pt.js": "./node_modules/moment/locale/pt.js",
	"./ro": "./node_modules/moment/locale/ro.js",
	"./ro.js": "./node_modules/moment/locale/ro.js",
	"./ru": "./node_modules/moment/locale/ru.js",
	"./ru.js": "./node_modules/moment/locale/ru.js",
	"./sd": "./node_modules/moment/locale/sd.js",
	"./sd.js": "./node_modules/moment/locale/sd.js",
	"./se": "./node_modules/moment/locale/se.js",
	"./se.js": "./node_modules/moment/locale/se.js",
	"./si": "./node_modules/moment/locale/si.js",
	"./si.js": "./node_modules/moment/locale/si.js",
	"./sk": "./node_modules/moment/locale/sk.js",
	"./sk.js": "./node_modules/moment/locale/sk.js",
	"./sl": "./node_modules/moment/locale/sl.js",
	"./sl.js": "./node_modules/moment/locale/sl.js",
	"./sq": "./node_modules/moment/locale/sq.js",
	"./sq.js": "./node_modules/moment/locale/sq.js",
	"./sr": "./node_modules/moment/locale/sr.js",
	"./sr-cyrl": "./node_modules/moment/locale/sr-cyrl.js",
	"./sr-cyrl.js": "./node_modules/moment/locale/sr-cyrl.js",
	"./sr.js": "./node_modules/moment/locale/sr.js",
	"./ss": "./node_modules/moment/locale/ss.js",
	"./ss.js": "./node_modules/moment/locale/ss.js",
	"./sv": "./node_modules/moment/locale/sv.js",
	"./sv.js": "./node_modules/moment/locale/sv.js",
	"./sw": "./node_modules/moment/locale/sw.js",
	"./sw.js": "./node_modules/moment/locale/sw.js",
	"./ta": "./node_modules/moment/locale/ta.js",
	"./ta.js": "./node_modules/moment/locale/ta.js",
	"./te": "./node_modules/moment/locale/te.js",
	"./te.js": "./node_modules/moment/locale/te.js",
	"./tet": "./node_modules/moment/locale/tet.js",
	"./tet.js": "./node_modules/moment/locale/tet.js",
	"./tg": "./node_modules/moment/locale/tg.js",
	"./tg.js": "./node_modules/moment/locale/tg.js",
	"./th": "./node_modules/moment/locale/th.js",
	"./th.js": "./node_modules/moment/locale/th.js",
	"./tk": "./node_modules/moment/locale/tk.js",
	"./tk.js": "./node_modules/moment/locale/tk.js",
	"./tl-ph": "./node_modules/moment/locale/tl-ph.js",
	"./tl-ph.js": "./node_modules/moment/locale/tl-ph.js",
	"./tlh": "./node_modules/moment/locale/tlh.js",
	"./tlh.js": "./node_modules/moment/locale/tlh.js",
	"./tr": "./node_modules/moment/locale/tr.js",
	"./tr.js": "./node_modules/moment/locale/tr.js",
	"./tzl": "./node_modules/moment/locale/tzl.js",
	"./tzl.js": "./node_modules/moment/locale/tzl.js",
	"./tzm": "./node_modules/moment/locale/tzm.js",
	"./tzm-latn": "./node_modules/moment/locale/tzm-latn.js",
	"./tzm-latn.js": "./node_modules/moment/locale/tzm-latn.js",
	"./tzm.js": "./node_modules/moment/locale/tzm.js",
	"./ug-cn": "./node_modules/moment/locale/ug-cn.js",
	"./ug-cn.js": "./node_modules/moment/locale/ug-cn.js",
	"./uk": "./node_modules/moment/locale/uk.js",
	"./uk.js": "./node_modules/moment/locale/uk.js",
	"./ur": "./node_modules/moment/locale/ur.js",
	"./ur.js": "./node_modules/moment/locale/ur.js",
	"./uz": "./node_modules/moment/locale/uz.js",
	"./uz-latn": "./node_modules/moment/locale/uz-latn.js",
	"./uz-latn.js": "./node_modules/moment/locale/uz-latn.js",
	"./uz.js": "./node_modules/moment/locale/uz.js",
	"./vi": "./node_modules/moment/locale/vi.js",
	"./vi.js": "./node_modules/moment/locale/vi.js",
	"./x-pseudo": "./node_modules/moment/locale/x-pseudo.js",
	"./x-pseudo.js": "./node_modules/moment/locale/x-pseudo.js",
	"./yo": "./node_modules/moment/locale/yo.js",
	"./yo.js": "./node_modules/moment/locale/yo.js",
	"./zh-cn": "./node_modules/moment/locale/zh-cn.js",
	"./zh-cn.js": "./node_modules/moment/locale/zh-cn.js",
	"./zh-hk": "./node_modules/moment/locale/zh-hk.js",
	"./zh-hk.js": "./node_modules/moment/locale/zh-hk.js",
	"./zh-mo": "./node_modules/moment/locale/zh-mo.js",
	"./zh-mo.js": "./node_modules/moment/locale/zh-mo.js",
	"./zh-tw": "./node_modules/moment/locale/zh-tw.js",
	"./zh-tw.js": "./node_modules/moment/locale/zh-tw.js"
};


function webpackContext(req) {
	var id = webpackContextResolve(req);
	return __webpack_require__(id);
}
function webpackContextResolve(req) {
	if(!__webpack_require__.o(map, req)) {
		var e = new Error("Cannot find module '" + req + "'");
		e.code = 'MODULE_NOT_FOUND';
		throw e;
	}
	return map[req];
}
webpackContext.keys = function webpackContextKeys() {
	return Object.keys(map);
};
webpackContext.resolve = webpackContextResolve;
module.exports = webpackContext;
webpackContext.id = "./node_modules/moment/locale sync recursive ^\\.\\/.*$";

/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["vendors-node_modules_bootstrap_dist_js_bootstrap_js-node_modules_chart_js_dist_Chart_js-node_-7f10bb"], () => (__webpack_exec__("./node_modules/jquery/dist/jquery.js"), __webpack_exec__("./node_modules/bootstrap/dist/js/bootstrap.js"), __webpack_exec__("./node_modules/select2/dist/js/select2.js"), __webpack_exec__("./node_modules/chart.js/dist/Chart.js"), __webpack_exec__("./assets/vendor/jquery.i18n/jquery.i18n.dist.js"), __webpack_exec__("./assets/vendor/bootstrap-typeahead.js"), __webpack_exec__("./assets/js/common/core_extensions.js"), __webpack_exec__("./assets/js/common/application.js"), __webpack_exec__("./assets/js/common/contributions-lists.js"), __webpack_exec__("./assets/js/adminstats.js"), __webpack_exec__("./assets/js/articleinfo.js"), __webpack_exec__("./assets/js/authorship.js"), __webpack_exec__("./assets/js/autoedits.js"), __webpack_exec__("./assets/js/blame.js"), __webpack_exec__("./assets/js/categoryedits.js"), __webpack_exec__("./assets/js/editcounter.js"), __webpack_exec__("./assets/js/globalcontribs.js"), __webpack_exec__("./assets/js/pages.js"), __webpack_exec__("./assets/js/topedits.js"), __webpack_exec__("./node_modules/bootstrap/dist/css/bootstrap.css"), __webpack_exec__("./node_modules/select2/dist/css/select2.css"), __webpack_exec__("./assets/css/application.scss"), __webpack_exec__("./assets/css/articleinfo.scss"), __webpack_exec__("./assets/css/autoedits.scss"), __webpack_exec__("./assets/css/blame.scss"), __webpack_exec__("./assets/css/categoryedits.scss"), __webpack_exec__("./assets/css/editcounter.scss"), __webpack_exec__("./assets/css/home.scss"), __webpack_exec__("./assets/css/meta.scss"), __webpack_exec__("./assets/css/pages.scss"), __webpack_exec__("./assets/css/topedits.scss"), __webpack_exec__("./assets/css/responsive.scss")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,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QUUsQ0FBRSxLQUFLLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiLFNBQVMsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNuQkMsRUFBRSxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ1pDLEdBQUcsRUFBRSxDQUFFLFVBQVUsQ0FBRTtJQUNuQixVQUFVLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDcEJDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWixPQUFPLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDakIsT0FBTyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2pCLFdBQVcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNyQkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYixTQUFTLEVBQUUsQ0FBRSxLQUFLLEVBQUUsSUFBSSxDQUFFO0lBQzFCQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLFVBQVUsRUFBRSxTQUFTLEVBQUUsU0FBUyxDQUFFO0lBQ3pDLFVBQVUsRUFBRSxDQUFFLFNBQVMsQ0FBRTtJQUN6QixVQUFVLEVBQUUsQ0FBRSxTQUFTLEVBQUUsU0FBUyxDQUFFO0lBQ3BDQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsVUFBVSxDQUFFO0lBQ25CQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsRUFBRSxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ1pDLEVBQUUsRUFBRSxDQUFFLE9BQU8sRUFBRSxTQUFTLENBQUU7SUFDMUJDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxVQUFVLENBQUU7SUFDbEJDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWkMsR0FBRyxFQUFFLENBQUUsU0FBUyxFQUFFLFNBQVMsQ0FBRTtJQUM3QkMsR0FBRyxFQUFFLENBQUUsVUFBVSxDQUFFO0lBQ25CQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLFNBQVMsQ0FBRTtJQUNqQixTQUFTLEVBQUUsQ0FBRSxTQUFTLENBQUU7SUFDeEIsU0FBUyxFQUFFLENBQUUsU0FBUyxDQUFFO0lBQ3hCLE9BQU8sRUFBRSxDQUFFLFNBQVMsRUFBRSxTQUFTLENBQUU7SUFDakMsT0FBTyxFQUFFLENBQUUsU0FBUyxDQUFFO0lBQ3RCLE9BQU8sRUFBRSxDQUFFLFNBQVMsRUFBRSxTQUFTLENBQUU7SUFDakNDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaLE9BQU8sRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNqQkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxTQUFTLENBQUU7SUFDakJDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxTQUFTLENBQUU7SUFDakIsU0FBUyxFQUFFLENBQUUsS0FBSyxDQUFFO0lBQ3BCQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYixTQUFTLEVBQUUsQ0FBRSxJQUFJLEVBQUUsSUFBSSxDQUFFO0lBQ3pCQyxFQUFFLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDWkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2J2UCxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYndQLEVBQUUsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNaQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxHQUFHLEVBQUUsQ0FBRSxJQUFJLENBQUU7SUFDYkMsR0FBRyxFQUFFLENBQUUsSUFBSSxDQUFFO0lBQ2JDLEdBQUcsRUFBRSxDQUFFLElBQUksQ0FBRTtJQUNiQyxH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QUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRSxDQUFDLENBQUM7TUFDUixJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRSxDQUFDLENBQUM7TUFDVCxLQUFLLEVBQUUsQ0FBQyxDQUFDO01BQ1QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFLENBQUMsQ0FBQztNQUNSLElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixNQUFNLEVBQUUsT0FBTztRQUNmLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLE9BQU87UUFDZCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLE1BQU0sRUFBRSxPQUFPO1FBQ2YsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFLENBQUMsQ0FBQztNQUNULElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLGtDQUFrQztRQUN6QyxLQUFLLEVBQUUscUNBQXFDO1FBQzVDLE1BQU0sRUFBRTtNQUNULENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxNQUFNLEVBQUUsOERBQThEO1FBQ3RFLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUUsaUJBQWlCO1FBQ3hCLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFLENBQUMsQ0FBQztNQUNSLElBQUksRUFBRTtRQUNMLEtBQUssRUFBRSxPQUFPO1FBQ2QsS0FBSyxFQUFFLDBCQUEwQjtRQUNqQyxNQUFNLEVBQUU7TUFDVCxDQUFDO01BQ0QsSUFBSSxFQUFFLENBQUMsQ0FBQztNQUNSLEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFLENBQUMsQ0FBQztNQUNULElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRSxpQkFBaUI7UUFDeEIsS0FBSyxFQUFFLCtDQUErQztRQUN0RCxNQUFNLEVBQUU7TUFDVCxDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sTUFBTSxFQUFFLDhEQUE4RDtRQUN0RSxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxPQUFPLEVBQUU7UUFDUixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRSxpQkFBaUI7UUFDeEIsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxNQUFNLEVBQUUsQ0FBQyxDQUFDO01BQ1YsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLHdDQUF3QztRQUMvQyxLQUFLLEVBQUUsK0NBQStDO1FBQ3RELE1BQU0sRUFBRTtNQUNULENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFLENBQUMsQ0FBQztNQUNULEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLE9BQU87UUFDZCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRSxDQUFDLENBQUM7TUFDVCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLHdFQUF3RTtRQUMvRSxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sS0FBSyxFQUFFLGdCQUFnQjtRQUN2QixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRSxpQkFBaUI7UUFDeEIsS0FBSyxFQUFFLG9CQUFvQjtRQUMzQixNQUFNLEVBQUU7TUFDVCxDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFLHVCQUF1QjtRQUM5QixLQUFLLEVBQUUsdUJBQXVCO1FBQzlCLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUUsT0FBTztRQUNkLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUUsd0VBQXdFO1FBQy9FLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsS0FBSyxFQUFFO1FBQ04sS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRSx3Q0FBd0M7UUFDL0MsS0FBSyxFQUFFLCtDQUErQztRQUN0RCxNQUFNLEVBQUU7TUFDVCxDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFLENBQUMsQ0FBQztNQUNSLElBQUksRUFBRTtRQUNMLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxLQUFLLEVBQUU7UUFDTixLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUUsQ0FBQyxDQUFDO01BQ1IsSUFBSSxFQUFFO1FBQ0wsS0FBSyxFQUFFO01BQ1IsQ0FBQztNQUNELEtBQUssRUFBRTtRQUNOLEtBQUssRUFBRTtNQUNSLENBQUM7TUFDRCxJQUFJLEVBQUU7UUFDTCxLQUFLLEVBQUU7TUFDUixDQUFDO01BQ0QsSUFBSSxFQUFFLENBQUMsQ0FBQztNQUNSLEtBQUssRUFBRSxDQUFDLENBQUM7TUFDVCxJQUFJLEVBQUUsQ0FBQyxD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Ozs7Ozs7Ozs7O0FDQUE7Ozs7Ozs7Ozs7Ozs7QUNBQTs7Ozs7Ozs7Ozs7OztBQ0FBOzs7Ozs7Ozs7Ozs7O0FDQUE7Ozs7Ozs7Ozs7Ozs7QUNBQTs7Ozs7Ozs7Ozs7OztBQ0FBOzs7Ozs7Ozs7Ozs7O0FDQUE7Ozs7Ozs7Ozs7Ozs7QUNBQTs7Ozs7Ozs7Ozs7QUNBQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBOzs7QUFHQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0EiLCJzb3VyY2VzIjpbIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvanMvYWRtaW5zdGF0cy5qcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvanMvYXJ0aWNsZWluZm8uanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2F1dGhvcnNoaXAuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2F1dG9lZGl0cy5qcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvanMvYmxhbWUuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2NhdGVnb3J5ZWRpdHMuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2NvbW1vbi9hcHBsaWNhdGlvbi5qcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvanMvY29tbW9uL2NvbnRyaWJ1dGlvbnMtbGlzdHMuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2NvbW1vbi9jb3JlX2V4dGVuc2lvbnMuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL2VkaXRjb3VudGVyLmpzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy9qcy9nbG9iYWxjb250cmlicy5qcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvanMvcGFnZXMuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2pzL3RvcGVkaXRzLmpzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy92ZW5kb3IvYm9vdHN0cmFwLXR5cGVhaGVhZC5qcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvdmVuZG9yL2pxdWVyeS5pMThuL2pxdWVyeS5pMThuLmRpc3QuanMiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2Nzcy9hcHBsaWNhdGlvbi5zY3NzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy9jc3MvYXJ0aWNsZWluZm8uc2NzcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvY3NzL2F1dG9lZGl0cy5zY3NzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy9jc3MvYmxhbWUuc2NzcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvY3NzL2NhdGVnb3J5ZWRpdHMuc2NzcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9hc3NldHMvY3NzL2VkaXRjb3VudGVyLnNjc3MiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2Nzcy9ob21lLnNjc3MiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2Nzcy9tZXRhLnNjc3MiLCJ3ZWJwYWNrOi8veHRvb2xzLy4vYXNzZXRzL2Nzcy9wYWdlcy5zY3NzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy9jc3MvcmVzcG9uc2l2ZS5zY3NzIiwid2VicGFjazovL3h0b29scy8uL2Fzc2V0cy9jc3MvdG9wZWRpdHMuc2NzcyIsIndlYnBhY2s6Ly94dG9vbHMvLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS8gc3luYyBeXFwuXFwvLiokIl0sInNvdXJjZXNDb250ZW50IjpbInh0b29scy5hZG1pbnN0YXRzID0ge307XG5cbiQoZnVuY3Rpb24gKCkge1xuICAgIHZhciAkcHJvamVjdElucHV0ID0gJCgnI3Byb2plY3RfaW5wdXQnKSxcbiAgICAgICAgbGFzdFByb2plY3QgPSAkcHJvamVjdElucHV0LnZhbCgpO1xuXG4gICAgLy8gRG9uJ3QgZG8gYW55dGhpbmcgaWYgdGhpcyBpc24ndCBhbiBBZG1pbiBTdGF0cyBwYWdlLlxuICAgIGlmICgkKCdib2R5LmFkbWluc3RhdHMsIGJvZHkucGF0cm9sbGVyc3RhdHMsIGJvZHkuc3Rld2FyZHN0YXRzJykubGVuZ3RoID09PSAwKSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICB4dG9vbHMuYXBwbGljYXRpb24uc2V0dXBNdWx0aVNlbGVjdExpc3RlbmVycygpO1xuXG4gICAgJCgnLmdyb3VwLXNlbGVjdG9yJykub24oJ2NoYW5nZScsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgJCgnLmFjdGlvbi1zZWxlY3RvcicpLmFkZENsYXNzKCdoaWRkZW4nKTtcbiAgICAgICAgJCgnLmFjdGlvbi1zZWxlY3Rvci0tJyArICQodGhpcykudmFsKCkpLnJlbW92ZUNsYXNzKCdoaWRkZW4nKTtcblxuICAgICAgICAvLyBVcGRhdGUgdGl0bGUgb2YgZm9ybS5cbiAgICAgICAgJCgnLnh0LXBhZ2UtdGl0bGUtLXRpdGxlJykudGV4dCgkLmkxOG4oJ3Rvb2wtJyArICQodGhpcykudmFsKCkgKyAnc3RhdHMnKSk7XG4gICAgICAgICQoJy54dC1wYWdlLXRpdGxlLS1kZXNjJykudGV4dCgkLmkxOG4oJ3Rvb2wtJyArICQodGhpcykudmFsKCkgKyAnc3RhdHMtZGVzYycpKTtcbiAgICAgICAgdmFyIHRpdGxlID0gJC5pMThuKCd0b29sLScgKyAkKHRoaXMpLnZhbCgpICsgJ3N0YXRzJykgKyAnIC0gJyArICQuaTE4bigneHRvb2xzLXRpdGxlJyk7XG4gICAgICAgIGRvY3VtZW50LnRpdGxlID0gdGl0bGU7XG4gICAgICAgIGhpc3RvcnkucmVwbGFjZVN0YXRlKHt9LCB0aXRsZSwgJy8nICsgJCh0aGlzKS52YWwoKSArICdzdGF0cycpO1xuXG4gICAgICAgIC8vIENoYW5nZSBwcm9qZWN0IHRvIE1ldGEgaWYgaXQncyBTdGV3YXJkIFN0YXRzLlxuICAgICAgICBpZiAoJ3N0ZXdhcmQnID09PSAkKHRoaXMpLnZhbCgpKSB7XG4gICAgICAgICAgICBsYXN0UHJvamVjdCA9ICRwcm9qZWN0SW5wdXQudmFsKCk7XG4gICAgICAgICAgICAkcHJvamVjdElucHV0LnZhbCgnbWV0YS53aWtpbWVkaWEub3JnJyk7XG4gICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAkcHJvamVjdElucHV0LnZhbChsYXN0UHJvamVjdCk7XG4gICAgICAgIH1cblxuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24uc2V0dXBNdWx0aVNlbGVjdExpc3RlbmVycygpO1xuICAgIH0pO1xufSk7XG4iLCJ4dG9vbHMuYXJ0aWNsZWluZm8gPSB7fTtcblxuJChmdW5jdGlvbiAoKSB7XG4gICAgaWYgKCEkKCdib2R5LmFydGljbGVpbmZvJykubGVuZ3RoKSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICBjb25zdCBzZXR1cFRvZ2dsZVRhYmxlID0gZnVuY3Rpb24gKCkge1xuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24uc2V0dXBUb2dnbGVUYWJsZShcbiAgICAgICAgICAgIHdpbmRvdy50ZXh0c2hhcmVzLFxuICAgICAgICAgICAgd2luZG93LnRleHRzaGFyZXNDaGFydCxcbiAgICAgICAgICAgICdwZXJjZW50YWdlJyxcbiAgICAgICAgICAgICQubm9vcFxuICAgICAgICApO1xuICAgIH07XG5cbiAgICBjb25zdCAkdGV4dHNoYXJlc0NvbnRhaW5lciA9ICQoJy50ZXh0c2hhcmVzLWNvbnRhaW5lcicpO1xuXG4gICAgaWYgKCR0ZXh0c2hhcmVzQ29udGFpbmVyWzBdKSB7XG4gICAgICAgIC8qKiBnbG9iYWw6IHh0QmFzZVVybCAqL1xuICAgICAgICBsZXQgdXJsID0geHRCYXNlVXJsICsgJ2F1dGhvcnNoaXAvJ1xuICAgICAgICAgICAgKyAkdGV4dHNoYXJlc0NvbnRhaW5lci5kYXRhKCdwcm9qZWN0JykgKyAnLydcbiAgICAgICAgICAgICsgJHRleHRzaGFyZXNDb250YWluZXIuZGF0YSgnYXJ0aWNsZScpICsgJy8nXG4gICAgICAgICAgICArICgkdGV4dHNoYXJlc0NvbnRhaW5lci5kYXRhKCdlbmQtZGF0ZScpID8gJHRleHRzaGFyZXNDb250YWluZXIuZGF0YSgnZW5kLWRhdGUnKSArICcvJyA6ICcnKTtcbiAgICAgICAgLy8gUmVtb3ZlIGV4dHJhbmVvdXMgZm9yd2FyZCBzbGFzaCB0aGF0IHdvdWxkIGNhdXNlIGEgMzAxIHJlZGlyZWN0LCBhbmQgcmVxdWVzdCBvdmVyIEhUVFAgaW5zdGVhZCBvZiBIVFRQUy5cbiAgICAgICAgdXJsID0gYCR7dXJsLnJlcGxhY2UoL1xcLyQvLCAnJyl9P2h0bWxvbmx5PXllc2A7XG5cbiAgICAgICAgJC5hamF4KHtcbiAgICAgICAgICAgIHVybDogdXJsLFxuICAgICAgICAgICAgdGltZW91dDogMzAwMDBcbiAgICAgICAgfSkuZG9uZShmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgJHRleHRzaGFyZXNDb250YWluZXIucmVwbGFjZVdpdGgoZGF0YSk7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24uYnVpbGRTZWN0aW9uT2Zmc2V0cygpO1xuICAgICAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwVG9jTGlzdGVuZXJzKCk7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24uc2V0dXBDb2x1bW5Tb3J0aW5nKCk7XG4gICAgICAgICAgICBzZXR1cFRvZ2dsZVRhYmxlKCk7XG4gICAgICAgIH0pLmZhaWwoZnVuY3Rpb24gKF94aHIsIF9zdGF0dXMsIG1lc3NhZ2UpIHtcbiAgICAgICAgICAgICR0ZXh0c2hhcmVzQ29udGFpbmVyLnJlcGxhY2VXaXRoKFxuICAgICAgICAgICAgICAgICQuaTE4bignYXBpLWVycm9yJywgJ0F1dGhvcnNoaXAgQVBJOiA8Y29kZT4nICsgbWVzc2FnZSArICc8L2NvZGU+JylcbiAgICAgICAgICAgICk7XG4gICAgICAgIH0pO1xuICAgIH0gZWxzZSBpZiAoJCgnLnRleHRzaGFyZXMtdGFibGUnKS5sZW5ndGgpIHtcbiAgICAgICAgc2V0dXBUb2dnbGVUYWJsZSgpO1xuICAgIH1cblxuICAgIC8vIFNldHVwIHRoZSBjaGFydHMuXG4gICAgY29uc3QgJGNoYXJ0ID0gJCgnI3llYXJfY291bnQnKSxcbiAgICAgICAgZGF0YXNldHMgPSAkY2hhcnQuZGF0YSgnZGF0YXNldHMnKTtcbiAgICBuZXcgQ2hhcnQoJGNoYXJ0LCB7XG4gICAgICAgIHR5cGU6ICdiYXInLFxuICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICBsYWJlbHM6ICRjaGFydC5kYXRhKCd5ZWFyLWxhYmVscycpLFxuICAgICAgICAgICAgZGF0YXNldHMsXG4gICAgICAgIH0sXG4gICAgICAgIG9wdGlvbnM6IHtcbiAgICAgICAgICAgIHJlc3BvbnNpdmU6IHRydWUsXG4gICAgICAgICAgICBsZWdlbmQ6IHtcbiAgICAgICAgICAgICAgICBkaXNwbGF5OiBmYWxzZSxcbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB0b29sdGlwczoge1xuICAgICAgICAgICAgICAgIG1vZGU6ICdsYWJlbCcsXG4gICAgICAgICAgICAgICAgY2FsbGJhY2tzOiB7XG4gICAgICAgICAgICAgICAgICAgIGxhYmVsOiBmdW5jdGlvbiAodG9vbHRpcEl0ZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBkYXRhc2V0c1t0b29sdGlwSXRlbS5kYXRhc2V0SW5kZXhdLmxhYmVsICsgJzogJ1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICsgKE51bWJlcih0b29sdGlwSXRlbS55TGFiZWwpKS50b0xvY2FsZVN0cmluZyhpMThuTGFuZyk7XG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICB9LFxuICAgICAgICAgICAgYmFyVmFsdWVTcGFjaW5nOiAyMCxcbiAgICAgICAgICAgIHNjYWxlczoge1xuICAgICAgICAgICAgICAgIHlBeGVzOiBbe1xuICAgICAgICAgICAgICAgICAgICBpZDogJ2VkaXRzJyxcbiAgICAgICAgICAgICAgICAgICAgdHlwZTogJ2xpbmVhcicsXG4gICAgICAgICAgICAgICAgICAgIHBvc2l0aW9uOiAnbGVmdCcsXG4gICAgICAgICAgICAgICAgICAgIHNjYWxlTGFiZWw6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGRpc3BsYXk6IHRydWUsXG4gICAgICAgICAgICAgICAgICAgICAgICBsYWJlbFN0cmluZzogJC5pMThuKCdlZGl0cycpLmNhcGl0YWxpemUoKSxcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgdGlja3M6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGJlZ2luQXRaZXJvOiB0cnVlLFxuICAgICAgICAgICAgICAgICAgICAgICAgY2FsbGJhY2s6IGZ1bmN0aW9uICh2YWx1ZSkge1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlmIChNYXRoLmZsb29yKHZhbHVlKSA9PT0gdmFsdWUpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHZhbHVlLnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nKTtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgICAgIGdyaWRMaW5lczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29sb3I6IHh0b29scy5hcHBsaWNhdGlvbi5jaGFydEdyaWRDb2xvclxuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgfSwge1xuICAgICAgICAgICAgICAgICAgICBpZDogJ3NpemUnLFxuICAgICAgICAgICAgICAgICAgICB0eXBlOiAnbGluZWFyJyxcbiAgICAgICAgICAgICAgICAgICAgcG9zaXRpb246ICdyaWdodCcsXG4gICAgICAgICAgICAgICAgICAgIHNjYWxlTGFiZWw6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGRpc3BsYXk6IHRydWUsXG4gICAgICAgICAgICAgICAgICAgICAgICBsYWJlbFN0cmluZzogJC5pMThuKCdzaXplJykuY2FwaXRhbGl6ZSgpLFxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICB0aWNrczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgYmVnaW5BdFplcm86IHRydWUsXG4gICAgICAgICAgICAgICAgICAgICAgICBjYWxsYmFjazogZnVuY3Rpb24gKHZhbHVlKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgaWYgKE1hdGguZmxvb3IodmFsdWUpID09PSB2YWx1ZSkge1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gdmFsdWUudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcpO1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgZ3JpZExpbmVzOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb2xvcjogeHRvb2xzLmFwcGxpY2F0aW9uLmNoYXJ0R3JpZENvbG9yXG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9XSxcbiAgICAgICAgICAgICAgICB4QXhlczogW3tcbiAgICAgICAgICAgICAgICAgICAgZ3JpZExpbmVzOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb2xvcjogeHRvb2xzLmFwcGxpY2F0aW9uLmNoYXJ0R3JpZENvbG9yXG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9XVxuICAgICAgICAgICAgfSxcbiAgICAgICAgfSxcbiAgICB9KTtcbn0pO1xuIiwiJChmdW5jdGlvbiAoKSB7XG4gICAgaWYgKCEkKCdib2R5LmF1dGhvcnNoaXAnKS5sZW5ndGgpIHtcbiAgICAgICAgcmV0dXJuO1xuICAgIH1cblxuICAgIC8vIEZvciB0aGUgZm9ybSBwYWdlLlxuICAgIGNvbnN0ICRzaG93U2VsZWN0b3IgPSAkKCcjc2hvd19zZWxlY3RvcicpO1xuICAgICRzaG93U2VsZWN0b3Iub24oJ2NoYW5nZScsIGUgPT4ge1xuICAgICAgICAkKCcuc2hvdy1vcHRpb24nKS5hZGRDbGFzcygnaGlkZGVuJylcbiAgICAgICAgICAgIC5maW5kKCdpbnB1dCcpLnByb3AoJ2Rpc2FibGVkJywgdHJ1ZSk7XG4gICAgICAgICQoYC5zaG93LW9wdGlvbi0tJHtlLnRhcmdldC52YWx1ZX1gKS5yZW1vdmVDbGFzcygnaGlkZGVuJylcbiAgICAgICAgICAgIC5maW5kKCdpbnB1dCcpLnByb3AoJ2Rpc2FibGVkJywgZmFsc2UpO1xuICAgIH0pO1xuICAgIHdpbmRvdy5vbmxvYWQgPSAoKSA9PiAkc2hvd1NlbGVjdG9yLnRyaWdnZXIoJ2NoYW5nZScpO1xuXG4gICAgaWYgKCQoJyNhdXRob3JzaGlwX2NoYXJ0JykubGVuZ3RoKSB7XG4gICAgICAgIHNldHVwQ2hhcnQoKTtcbiAgICB9XG59KTtcblxuZnVuY3Rpb24gc2V0dXBDaGFydCgpXG57XG4gICAgY29uc3QgJGNoYXJ0ID0gJCgnI2F1dGhvcnNoaXBfY2hhcnQnKSxcbiAgICAgICAgcGVyY2VudGFnZXMgPSBPYmplY3Qua2V5cygkY2hhcnQuZGF0YSgnbGlzdCcpKS5zbGljZSgwLCAxMCkubWFwKGF1dGhvciA9PiB7XG4gICAgICAgICAgICByZXR1cm4gJGNoYXJ0LmRhdGEoJ2xpc3QnKVthdXRob3JdLnBlcmNlbnRhZ2U7XG4gICAgICAgIH0pO1xuXG4gICAgLy8gQWRkIHRoZSBcIk90aGVyc1wiIHNsaWNlIGlmIGFwcGxpY2FibGUuXG4gICAgaWYgKCRjaGFydC5kYXRhKCdvdGhlcnMnKSkge1xuICAgICAgICBwZXJjZW50YWdlcy5wdXNoKCRjaGFydC5kYXRhKCdvdGhlcnMnKS5wZXJjZW50YWdlKTtcbiAgICB9XG5cbiAgICBjb25zdCBhdXRob3JzaGlwQ2hhcnQgPSBuZXcgQ2hhcnQoJGNoYXJ0LCB7XG4gICAgICAgIHR5cGU6ICdwaWUnLFxuICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICBsYWJlbHM6ICRjaGFydC5kYXRhKCdsYWJlbHMnKSxcbiAgICAgICAgICAgIGRhdGFzZXRzOiBbe1xuICAgICAgICAgICAgICAgIGRhdGE6IHBlcmNlbnRhZ2VzLFxuICAgICAgICAgICAgICAgIGJhY2tncm91bmRDb2xvcjogJGNoYXJ0LmRhdGEoJ2NvbG9ycycpLFxuICAgICAgICAgICAgICAgIGJvcmRlckNvbG9yOiAkY2hhcnQuZGF0YSgnY29sb3JzJyksXG4gICAgICAgICAgICAgICAgYm9yZGVyV2lkdGg6IDFcbiAgICAgICAgICAgIH1dXG4gICAgICAgIH0sXG4gICAgICAgIG9wdGlvbnM6IHtcbiAgICAgICAgICAgIGFzcGVjdFJhdGlvOiAxLFxuICAgICAgICAgICAgbGVnZW5kOiB7XG4gICAgICAgICAgICAgICAgZGlzcGxheTogZmFsc2VcbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB0b29sdGlwczoge1xuICAgICAgICAgICAgICAgIGNhbGxiYWNrczoge1xuICAgICAgICAgICAgICAgICAgICBsYWJlbDogZnVuY3Rpb24gKHRvb2x0aXBJdGVtLCBjaGFydERhdGEpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IGxhYmVsID0gY2hhcnREYXRhLmxhYmVsc1t0b29sdGlwSXRlbS5pbmRleF0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWUgPSBjaGFydERhdGEuZGF0YXNldHNbMF0uZGF0YVt0b29sdGlwSXRlbS5pbmRleF0gLyAxMDA7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gbGFiZWwgKyAnOiAnICsgdmFsdWUudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcsIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBzdHlsZTogJ3BlcmNlbnQnLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIG1heGltdW1GcmFjdGlvbkRpZ2l0czogMVxuICAgICAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cbiAgICB9KTtcbn1cbiIsInh0b29scy5hdXRvZWRpdHMgPSB7fTtcblxuJChmdW5jdGlvbiAoKSB7XG4gICAgaWYgKCEkKCdib2R5LmF1dG9lZGl0cycpLmxlbmd0aCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgdmFyICRjb250cmlidXRpb25zQ29udGFpbmVyID0gJCgnLmNvbnRyaWJ1dGlvbnMtY29udGFpbmVyJyksXG4gICAgICAgICR0b29sU2VsZWN0b3IgPSAkKCcjdG9vbF9zZWxlY3RvcicpO1xuXG4gICAgLy8gRm9yIHRoZSBmb3JtIHBhZ2UuXG4gICAgaWYgKCR0b29sU2VsZWN0b3IubGVuZ3RoKSB7XG4gICAgICAgIHh0b29scy5hdXRvZWRpdHMuZmV0Y2hUb29scyA9IGZ1bmN0aW9uIChwcm9qZWN0KSB7XG4gICAgICAgICAgICAkdG9vbFNlbGVjdG9yLnByb3AoJ2Rpc2FibGVkJywgdHJ1ZSk7XG4gICAgICAgICAgICAkLmdldCgnL2FwaS9wcm9qZWN0L2F1dG9tYXRlZF90b29scy8nICsgcHJvamVjdCkuZG9uZShmdW5jdGlvbiAodG9vbHMpIHtcbiAgICAgICAgICAgICAgICBpZiAodG9vbHMuZXJyb3IpIHtcbiAgICAgICAgICAgICAgICAgICAgJHRvb2xTZWxlY3Rvci5wcm9wKCdkaXNhYmxlZCcsIGZhbHNlKTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuOyAvLyBBYm9ydCwgcHJvamVjdCB3YXMgaW52YWxpZC5cbiAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICAvLyBUaGVzZSBhcmVuJ3QgdG9vbHMsIGp1c3QgbWV0YWRhdGEgaW4gdGhlIEFQSSByZXNwb25zZS5cbiAgICAgICAgICAgICAgICBkZWxldGUgdG9vbHMucHJvamVjdDtcbiAgICAgICAgICAgICAgICBkZWxldGUgdG9vbHMuZWxhcHNlZF90aW1lO1xuXG4gICAgICAgICAgICAgICAgJHRvb2xTZWxlY3Rvci5odG1sKFxuICAgICAgICAgICAgICAgICAgICAnPG9wdGlvbiB2YWx1ZT1cIm5vbmVcIj4nICsgJC5pMThuKCdub25lJykgKyAnPC9vcHRpb24+JyArXG4gICAgICAgICAgICAgICAgICAgICc8b3B0aW9uIHZhbHVlPVwiYWxsXCI+JyArICQuaTE4bignYWxsJykgKyAnPC9vcHRpb24+J1xuICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICAgICAgT2JqZWN0LmtleXModG9vbHMpLmZvckVhY2goZnVuY3Rpb24gKHRvb2wpIHtcbiAgICAgICAgICAgICAgICAgICAgJHRvb2xTZWxlY3Rvci5hcHBlbmQoXG4gICAgICAgICAgICAgICAgICAgICAgICAnPG9wdGlvbiB2YWx1ZT1cIicgKyB0b29sICsgJ1wiPicgKyAodG9vbHNbdG9vbF0ubGFiZWwgfHwgdG9vbCkgKyAnPC9vcHRpb24+J1xuICAgICAgICAgICAgICAgICAgICApO1xuICAgICAgICAgICAgICAgIH0pO1xuXG4gICAgICAgICAgICAgICAgJHRvb2xTZWxlY3Rvci5wcm9wKCdkaXNhYmxlZCcsIGZhbHNlKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICB9O1xuXG4gICAgICAgICQoZG9jdW1lbnQpLnJlYWR5KGZ1bmN0aW9uICgpIHtcbiAgICAgICAgICAgICQoJyNwcm9qZWN0X2lucHV0Jykub24oJ2NoYW5nZS5hdXRvZWRpdHMnLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICAgICAgeHRvb2xzLmF1dG9lZGl0cy5mZXRjaFRvb2xzKCQoJyNwcm9qZWN0X2lucHV0JykudmFsKCkpO1xuICAgICAgICAgICAgfSk7XG4gICAgICAgIH0pO1xuXG4gICAgICAgIHh0b29scy5hdXRvZWRpdHMuZmV0Y2hUb29scygkKCcjcHJvamVjdF9pbnB1dCcpLnZhbCgpKTtcblxuICAgICAgICAvLyBBbGwgdGhlIG90aGVyIGNvZGUgYmVsb3cgb25seSBhcHBsaWVzIHRvIHJlc3VsdCBwYWdlcy5cbiAgICAgICAgcmV0dXJuO1xuICAgIH1cblxuICAgIC8vIEZvciByZXN1bHQgcGFnZXMgb25seS4uLlxuXG4gICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwVG9nZ2xlVGFibGUod2luZG93LmNvdW50c0J5VG9vbCwgd2luZG93LnRvb2xzQ2hhcnQsICdjb3VudCcsIGZ1bmN0aW9uIChuZXdEYXRhKSB7XG4gICAgICAgIHZhciB0b3RhbCA9IDA7XG4gICAgICAgIE9iamVjdC5rZXlzKG5ld0RhdGEpLmZvckVhY2goZnVuY3Rpb24gKHRvb2wpIHtcbiAgICAgICAgICAgIHRvdGFsICs9IHBhcnNlSW50KG5ld0RhdGFbdG9vbF0uY291bnQsIDEwKTtcbiAgICAgICAgfSk7XG4gICAgICAgIHZhciB0b29sc0NvdW50ID0gT2JqZWN0LmtleXMobmV3RGF0YSkubGVuZ3RoO1xuICAgICAgICAvKiogZ2xvYmFsOiBpMThuTGFuZyAqL1xuICAgICAgICAkKCcudG9vbHMtLXRvb2xzJykudGV4dChcbiAgICAgICAgICAgIHRvb2xzQ291bnQudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcpICsgXCIgXCIgK1xuICAgICAgICAgICAgJC5pMThuKCdudW0tdG9vbHMnLCB0b29sc0NvdW50KVxuICAgICAgICApO1xuICAgICAgICAkKCcudG9vbHMtLWNvdW50JykudGV4dCh0b3RhbC50b0xvY2FsZVN0cmluZyhpMThuTGFuZykpO1xuICAgIH0pO1xuXG4gICAgaWYgKCRjb250cmlidXRpb25zQ29udGFpbmVyLmxlbmd0aCkge1xuICAgICAgICAvLyBMb2FkIHRoZSBjb250cmlidXRpb25zIGJyb3dzZXIsIG9yIHNldCB1cCB0aGUgbGlzdGVuZXJzIGlmIGl0IGlzIGFscmVhZHkgcHJlc2VudC5cbiAgICAgICAgdmFyIGluaXRGdW5jID0gJCgnLmNvbnRyaWJ1dGlvbnMtdGFibGUnKS5sZW5ndGggPyAnc2V0dXBDb250cmlidXRpb25zTmF2TGlzdGVuZXJzJyA6ICdsb2FkQ29udHJpYnV0aW9ucyc7XG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbltpbml0RnVuY10oXG4gICAgICAgICAgICBmdW5jdGlvbiAocGFyYW1zKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIGAke3BhcmFtcy50YXJnZXR9LWNvbnRyaWJ1dGlvbnMvJHtwYXJhbXMucHJvamVjdH0vJHtwYXJhbXMudXNlcm5hbWV9YCArXG4gICAgICAgICAgICAgICAgICAgIGAvJHtwYXJhbXMubmFtZXNwYWNlfS8ke3BhcmFtcy5zdGFydH0vJHtwYXJhbXMuZW5kfWA7XG4gICAgICAgICAgICB9LFxuICAgICAgICAgICAgJGNvbnRyaWJ1dGlvbnNDb250YWluZXIuZGF0YSgndGFyZ2V0JylcbiAgICAgICAgKTtcbiAgICB9XG59KTtcbiIsInh0b29scy5ibGFtZSA9IHt9O1xuXG4kKGZ1bmN0aW9uICgpIHtcbiAgICBpZiAoISQoJ2JvZHkuYmxhbWUnKS5sZW5ndGgpIHtcbiAgICAgICAgcmV0dXJuO1xuICAgIH1cblxuICAgIGlmICgkKCcuZGlmZi1lbXB0eScpLmxlbmd0aCA9PT0gJCgnLmRpZmYgdHInKS5sZW5ndGggLSAxKSB7XG4gICAgICAgICQoJy5kaWZmLWVtcHR5JykuZXEoMClcbiAgICAgICAgICAgIC50ZXh0KGAoJHskLmkxOG4oJ2RpZmYtZW1wdHknKS50b0xvd2VyQ2FzZSgpfSlgKVxuICAgICAgICAgICAgLmFkZENsYXNzKCd0ZXh0LW11dGVkIHRleHQtY2VudGVyJylcbiAgICAgICAgICAgIC5wcm9wKCd3aWR0aCcsICcyMCUnKTtcbiAgICB9XG5cbiAgICAkKCcuZGlmZi1hZGRlZGxpbmUnKS5lYWNoKGZ1bmN0aW9uICgpIHtcbiAgICAgICAgLy8gRXNjYXBlIHF1ZXJ5IHRvIG1ha2UgcmVnZXgtc2FmZS5cbiAgICAgICAgY29uc3QgZXNjYXBlZFF1ZXJ5ID0geHRvb2xzLmJsYW1lLnF1ZXJ5LnJlcGxhY2UoL1stXFwvXFxcXF4kKis/LigpfFtcXF17fV0vZywgJ1xcXFwkJicpO1xuXG4gICAgICAgIGNvbnN0IGhpZ2hsaWdodE1hdGNoID0gc2VsZWN0b3IgPT4ge1xuICAgICAgICAgICAgY29uc3QgcmVnZXggPSBuZXcgUmVnRXhwKGAoJHtlc2NhcGVkUXVlcnl9KWAsICdnaScpO1xuICAgICAgICAgICAgJChzZWxlY3RvcikuaHRtbChcbiAgICAgICAgICAgICAgICAkKHNlbGVjdG9yKS5odG1sKCkucmVwbGFjZShyZWdleCwgYDxzdHJvbmc+JDE8L3N0cm9uZz5gKVxuICAgICAgICAgICAgKTtcbiAgICAgICAgfTtcblxuICAgICAgICBpZiAoJCh0aGlzKS5maW5kKCcuZGlmZmNoYW5nZS1pbmxpbmUnKS5sZW5ndGgpIHtcbiAgICAgICAgICAgICQoJy5kaWZmY2hhbmdlLWlubGluZScpLmVhY2goZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgICAgIGhpZ2hsaWdodE1hdGNoKHRoaXMpO1xuICAgICAgICAgICAgfSk7XG4gICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICBoaWdobGlnaHRNYXRjaCh0aGlzKTtcbiAgICAgICAgfVxuICAgIH0pO1xuXG4gICAgLy8gSGFuZGxlcyB0aGUgXCJTaG93XCIgZHJvcGRvd24sIHNob3cvaGlkaW5nIHRoZSBhc3NvY2lhdGVkIGlucHV0IGZpZWxkIGFjY29yZGluZ2x5LlxuICAgIGNvbnN0ICRzaG93U2VsZWN0b3IgPSAkKCcjc2hvd19zZWxlY3RvcicpO1xuICAgICRzaG93U2VsZWN0b3Iub24oJ2NoYW5nZScsIGUgPT4ge1xuICAgICAgICAkKCcuc2hvdy1vcHRpb24nKS5hZGRDbGFzcygnaGlkZGVuJylcbiAgICAgICAgICAgIC5maW5kKCdpbnB1dCcpLnByb3AoJ2Rpc2FibGVkJywgdHJ1ZSk7XG4gICAgICAgICQoYC5zaG93LW9wdGlvbi0tJHtlLnRhcmdldC52YWx1ZX1gKS5yZW1vdmVDbGFzcygnaGlkZGVuJylcbiAgICAgICAgICAgIC5maW5kKCdpbnB1dCcpLnByb3AoJ2Rpc2FibGVkJywgZmFsc2UpO1xuICAgIH0pO1xuICAgIHdpbmRvdy5vbmxvYWQgPSAoKSA9PiAkc2hvd1NlbGVjdG9yLnRyaWdnZXIoJ2NoYW5nZScpO1xufSk7XG4iLCJ4dG9vbHMuY2F0ZWdvcnllZGl0cyA9IHt9O1xuXG4kKGZ1bmN0aW9uICgpIHtcbiAgICBpZiAoISQoJ2JvZHkuY2F0ZWdvcnllZGl0cycpLmxlbmd0aCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgJChkb2N1bWVudCkucmVhZHkoZnVuY3Rpb24gKCkge1xuICAgICAgICB4dG9vbHMuY2F0ZWdvcnllZGl0cy4kc2VsZWN0MklucHV0ID0gJCgnI2NhdGVnb3J5X3NlbGVjdG9yJyk7XG5cbiAgICAgICAgc2V0dXBDYXRlZ29yeUlucHV0KCk7XG5cbiAgICAgICAgJCgnI3Byb2plY3RfaW5wdXQnKS5vbigneHRvb2xzLnByb2plY3RMb2FkZWQnLCBmdW5jdGlvbiAoX2UsIGRhdGEpIHtcbiAgICAgICAgICAgIC8qKiBnbG9iYWw6IHh0QmFzZVVybCAqL1xuICAgICAgICAgICAgJC5nZXQoeHRCYXNlVXJsICsgJ2FwaS9wcm9qZWN0L25hbWVzcGFjZXMvJyArIGRhdGEucHJvamVjdCkuZG9uZShmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgICAgIHNldHVwQ2F0ZWdvcnlJbnB1dChkYXRhLmFwaSwgZGF0YS5uYW1lc3BhY2VzWzE0XSk7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfSk7XG5cbiAgICAgICAgJCgnZm9ybScpLm9uKCdzdWJtaXQnLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICAkKCcjY2F0ZWdvcnlfaW5wdXQnKS52YWwoIC8vIEhpZGRlbiBpbnB1dCBmaWVsZFxuICAgICAgICAgICAgICAgIHh0b29scy5jYXRlZ29yeWVkaXRzLiRzZWxlY3QySW5wdXQudmFsKCkuam9pbignfCcpXG4gICAgICAgICAgICApO1xuICAgICAgICB9KTtcblxuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24uc2V0dXBUb2dnbGVUYWJsZSh3aW5kb3cuY291bnRzQnlDYXRlZ29yeSwgd2luZG93LmNhdGVnb3J5Q2hhcnQsICdlZGl0Q291bnQnLCBmdW5jdGlvbiAobmV3RGF0YSkge1xuICAgICAgICAgICAgdmFyIHRvdGFsRWRpdHMgPSAwLFxuICAgICAgICAgICAgICAgIHRvdGFsUGFnZXMgPSAwO1xuICAgICAgICAgICAgT2JqZWN0LmtleXMobmV3RGF0YSkuZm9yRWFjaChmdW5jdGlvbiAoY2F0ZWdvcnkpIHtcbiAgICAgICAgICAgICAgICB0b3RhbEVkaXRzICs9IHBhcnNlSW50KG5ld0RhdGFbY2F0ZWdvcnldLmVkaXRDb3VudCwgMTApO1xuICAgICAgICAgICAgICAgIHRvdGFsUGFnZXMgKz0gcGFyc2VJbnQobmV3RGF0YVtjYXRlZ29yeV0ucGFnZUNvdW50LCAxMCk7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgICAgIHZhciBjYXRlZ29yaWVzQ291bnQgPSBPYmplY3Qua2V5cyhuZXdEYXRhKS5sZW5ndGg7XG4gICAgICAgICAgICAvKiogZ2xvYmFsOiBpMThuTGFuZyAqL1xuICAgICAgICAgICAgJCgnLmNhdGVnb3J5LS1jYXRlZ29yeScpLnRleHQoXG4gICAgICAgICAgICAgICAgY2F0ZWdvcmllc0NvdW50LnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nKSArIFwiIFwiICtcbiAgICAgICAgICAgICAgICAkLmkxOG4oJ251bS1jYXRlZ29yaWVzJywgY2F0ZWdvcmllc0NvdW50KVxuICAgICAgICAgICAgKTtcbiAgICAgICAgICAgICQoJy5jYXRlZ29yeS0tY291bnQnKS50ZXh0KHRvdGFsRWRpdHMudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcpKTtcbiAgICAgICAgICAgICQoJy5jYXRlZ29yeS0tcGVyY2VudC1vZi1lZGl0LWNvdW50JykudGV4dChcbiAgICAgICAgICAgICAgICAoKHRvdGFsRWRpdHMgLyB4dG9vbHMuY2F0ZWdvcnllZGl0cy51c2VyRWRpdENvdW50KS50b0xvY2FsZVN0cmluZyhpMThuTGFuZykgKiAxMDApICsgJyUnXG4gICAgICAgICAgICApO1xuICAgICAgICAgICAgJCgnLmNhdGVnb3J5LS1wYWdlcycpLnRleHQodG90YWxQYWdlcy50b0xvY2FsZVN0cmluZyhpMThuTGFuZykpO1xuICAgICAgICB9KTtcblxuICAgICAgICBpZiAoJCgnLmNvbnRyaWJ1dGlvbnMtY29udGFpbmVyJykubGVuZ3RoKSB7XG4gICAgICAgICAgICBsb2FkQ2F0ZWdvcnlFZGl0cygpO1xuICAgICAgICB9XG4gICAgfSk7XG59KTtcblxuLyoqXG4gKiBMb2FkIGNhdGVnb3J5IGVkaXRzIEhUTUwgdmlhIEFKQVgsIHRvIG5vdCBzbG93IGRvd24gdGhlIGluaXRpYWwgcGFnZSBsb2FkLiBPbmx5IGxvYWQgaWYgY29udGFpbmVyIGlzIHByZXNlbnQsXG4gKiB3aGljaCBpcyBtaXNzaW5nIG9uIGluZGV4IHBhZ2VzIGFuZCBpbiBzdWJyb3V0ZXMsIGUuZy4gY2F0ZWdvcnllZGl0cy1jb250cmlidXRpb25zLCBldGMuXG4gKi9cbmZ1bmN0aW9uIGxvYWRDYXRlZ29yeUVkaXRzKClcbntcbiAgICAvLyBMb2FkIHRoZSBjb250cmlidXRpb25zIGJyb3dzZXIsIG9yIHNldCB1cCB0aGUgbGlzdGVuZXJzIGlmIGl0IGlzIGFscmVhZHkgcHJlc2VudC5cbiAgICB2YXIgaW5pdEZ1bmMgPSAkKCcuY29udHJpYnV0aW9ucy10YWJsZScpLmxlbmd0aCA/ICdzZXR1cENvbnRyaWJ1dGlvbnNOYXZMaXN0ZW5lcnMnIDogJ2xvYWRDb250cmlidXRpb25zJztcbiAgICB4dG9vbHMuYXBwbGljYXRpb25baW5pdEZ1bmNdKFxuICAgICAgICBmdW5jdGlvbiAocGFyYW1zKSB7XG4gICAgICAgICAgICByZXR1cm4gJ2NhdGVnb3J5ZWRpdHMtY29udHJpYnV0aW9ucy8nICsgcGFyYW1zLnByb2plY3QgKyAnLycgKyBwYXJhbXMudXNlcm5hbWUgKyAnLycgK1xuICAgICAgICAgICAgICAgIHBhcmFtcy5jYXRlZ29yaWVzICsgJy8nICsgcGFyYW1zLnN0YXJ0ICsgJy8nICsgcGFyYW1zLmVuZDtcbiAgICAgICAgfSxcbiAgICAgICAgJ0NhdGVnb3J5J1xuICAgICk7XG59XG5cbi8qKlxuICogU2V0dXBzIHRoZSBTZWxlY3QyIGNvbnRyb2wgdG8gc2VhcmNoIGZvciBwYWdlcyBpbiB0aGUgQ2F0ZWdvcnkgbmFtZXNwYWNlLlxuICogQHBhcmFtIHtTdHJpbmd9IFthcGldIEZ1bGx5IHF1YWxpZmllZCBBUEkgZW5kcG9pbnQuXG4gKiBAcGFyYW0ge1N0cmluZ30gW25zXSBOYW1lIG9mIHRoZSBDYXRlZ29yeSBuYW1lc3BhY2UuXG4gKi9cbmZ1bmN0aW9uIHNldHVwQ2F0ZWdvcnlJbnB1dChhcGksIG5zKVxue1xuICAgIC8vIEZpcnN0IGRlc3Ryb3kgYW55IGV4aXN0aW5nIFNlbGVjdDIgaW5wdXRzLlxuICAgIGlmICh4dG9vbHMuY2F0ZWdvcnllZGl0cy4kc2VsZWN0MklucHV0LmRhdGEoJ3NlbGVjdDInKSkge1xuICAgICAgICB4dG9vbHMuY2F0ZWdvcnllZGl0cy4kc2VsZWN0MklucHV0Lm9mZignY2hhbmdlJyk7XG4gICAgICAgIHh0b29scy5jYXRlZ29yeWVkaXRzLiRzZWxlY3QySW5wdXQuc2VsZWN0MigndmFsJywgbnVsbCk7XG4gICAgICAgIHh0b29scy5jYXRlZ29yeWVkaXRzLiRzZWxlY3QySW5wdXQuc2VsZWN0MignZGF0YScsIG51bGwpO1xuICAgICAgICB4dG9vbHMuY2F0ZWdvcnllZGl0cy4kc2VsZWN0MklucHV0LnNlbGVjdDIoJ2Rlc3Ryb3knKTtcbiAgICB9XG5cbiAgICB2YXIgbnNOYW1lID0gbnMgfHwgeHRvb2xzLmNhdGVnb3J5ZWRpdHMuJHNlbGVjdDJJbnB1dC5kYXRhKCducycpO1xuXG4gICAgdmFyIHBhcmFtcyA9IHtcbiAgICAgICAgYWpheDoge1xuICAgICAgICAgICAgdXJsOiBhcGkgfHwgeHRvb2xzLmNhdGVnb3J5ZWRpdHMuJHNlbGVjdDJJbnB1dC5kYXRhKCdhcGknKSxcbiAgICAgICAgICAgIGRhdGFUeXBlOiAnanNvbnAnLFxuICAgICAgICAgICAganNvbnBDYWxsYmFjazogJ2NhdGVnb3J5U3VnZ2VzdGlvbkNhbGxiYWNrJyxcbiAgICAgICAgICAgIGRlbGF5OiAyMDAsXG4gICAgICAgICAgICBkYXRhOiBmdW5jdGlvbiAoc2VhcmNoKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIHtcbiAgICAgICAgICAgICAgICAgICAgYWN0aW9uOiAncXVlcnknLFxuICAgICAgICAgICAgICAgICAgICBsaXN0OiAncHJlZml4c2VhcmNoJyxcbiAgICAgICAgICAgICAgICAgICAgZm9ybWF0OiAnanNvbicsXG4gICAgICAgICAgICAgICAgICAgIHBzc2VhcmNoOiBzZWFyY2gudGVybSB8fCAnJyxcbiAgICAgICAgICAgICAgICAgICAgcHNuYW1lc3BhY2U6IDE0LFxuICAgICAgICAgICAgICAgICAgICBjaXJydXNVc2VDb21wbGV0aW9uU3VnZ2VzdGVyOiAneWVzJ1xuICAgICAgICAgICAgICAgIH07XG4gICAgICAgICAgICB9LFxuICAgICAgICAgICAgcHJvY2Vzc1Jlc3VsdHM6IGZ1bmN0aW9uIChkYXRhKSB7XG4gICAgICAgICAgICAgICAgdmFyIHF1ZXJ5ID0gZGF0YSA/IGRhdGEucXVlcnkgOiB7fSxcbiAgICAgICAgICAgICAgICAgICAgcmVzdWx0cyA9IFtdO1xuXG4gICAgICAgICAgICAgICAgaWYgKHF1ZXJ5ICYmIHF1ZXJ5LnByZWZpeHNlYXJjaC5sZW5ndGgpIHtcbiAgICAgICAgICAgICAgICAgICAgcmVzdWx0cyA9IHF1ZXJ5LnByZWZpeHNlYXJjaC5tYXAoZnVuY3Rpb24gKGVsZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhciB0aXRsZSA9IGVsZW0udGl0bGUucmVwbGFjZShuZXcgUmVnRXhwKCdeJyArIG5zTmFtZSArICc6JyksICcnKTtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgaWQ6IHRpdGxlLnNjb3JlKCksXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdGV4dDogdGl0bGVcbiAgICAgICAgICAgICAgICAgICAgICAgIH07XG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIHJldHVybiB7cmVzdWx0czogcmVzdWx0c31cbiAgICAgICAgICAgIH1cbiAgICAgICAgfSxcbiAgICAgICAgcGxhY2Vob2xkZXI6ICQuaTE4bignY2F0ZWdvcnktc2VhcmNoJyksXG4gICAgICAgIG1heGltdW1TZWxlY3Rpb25MZW5ndGg6IDEwLFxuICAgICAgICBtaW5pbXVtSW5wdXRMZW5ndGg6IDFcbiAgICB9O1xuXG4gICAgeHRvb2xzLmNhdGVnb3J5ZWRpdHMuJHNlbGVjdDJJbnB1dC5zZWxlY3QyKHBhcmFtcyk7XG59XG4iLCJjb25zdCAkID0gcmVxdWlyZSgnanF1ZXJ5Jyk7XG5cbnh0b29scyA9IHt9O1xueHRvb2xzLmFwcGxpY2F0aW9uID0ge307XG54dG9vbHMuYXBwbGljYXRpb24udmFycyA9IHtcbiAgICBzZWN0aW9uT2Zmc2V0OiB7fSxcbn07XG54dG9vbHMuYXBwbGljYXRpb24uY2hhcnRHcmlkQ29sb3IgPSAncmdiYSgwLCAwLCAwLCAwLjEpJztcblxuLy8gTWFrZSBqUXVlcnkgYW5kIHh0b29scyBnbG9iYWwgKGZvciBub3cpLlxuZ2xvYmFsLiQgPSBnbG9iYWwualF1ZXJ5ID0gJDtcbmdsb2JhbC54dG9vbHMgPSB4dG9vbHM7XG5cbmlmICh3aW5kb3cubWF0Y2hNZWRpYShcIihwcmVmZXJzLWNvbG9yLXNjaGVtZTogZGFyaylcIikubWF0Y2hlcykge1xuICAgIENoYXJ0LmRlZmF1bHRzLmdsb2JhbC5kZWZhdWx0Rm9udENvbG9yID0gJyNBQUEnO1xuICAgIC8vIENhbid0IHNldCBhIGdsb2JhbCBkZWZhdWx0IHdpdGggb3VyIHZlcnNpb24gb2YgQ2hhcnQuanMsIGFwcGFyZW50bHksXG4gICAgLy8gc28gZWFjaCBjaGFydCBpbml0aWFsaXphdGlvbiBtdXN0IGV4cGxpY2l0bHkgc2V0IHRoZSBncmlkIGxpbmUgY29sb3IuXG4gICAgeHRvb2xzLmFwcGxpY2F0aW9uLmNoYXJ0R3JpZENvbG9yID0gJyMzMzMnO1xufVxuXG4vKiogZ2xvYmFsOiBpMThuTGFuZyAqL1xuLyoqIGdsb2JhbDogaTE4blBhdGhzICovXG4kLmkxOG4oe1xuICAgIGxvY2FsZTogaTE4bkxhbmdcbn0pLmxvYWQoaTE4blBhdGhzKTtcblxuJChmdW5jdGlvbiAoKSB7XG4gICAgLy8gVGhlICQoKSBhcm91bmQgdGhpcyBjb2RlIGFwcGFyZW50bHkgaXNuJ3QgZW5vdWdoIGZvciBXZWJwYWNrLCBuZWVkIGFub3RoZXIgZG9jdW1lbnQtcmVhZHkgY2hlY2suXG4gICAgJChkb2N1bWVudCkucmVhZHkoZnVuY3Rpb24gKCkge1xuICAgICAgICAvLyBUT0RPOiBtb3ZlIHRoZXNlIGxpc3RlbmVycyB0byBhIHNldHVwIGZ1bmN0aW9uIGFuZCBkb2N1bWVudCBob3cgdG8gdXNlIGl0LlxuICAgICAgICAkKCcueHQtaGlkZScpLm9uKCdjbGljaycsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgICAgICQodGhpcykuaGlkZSgpO1xuICAgICAgICAgICAgJCh0aGlzKS5zaWJsaW5ncygnLnh0LXNob3cnKS5zaG93KCk7XG5cbiAgICAgICAgICAgIGlmICgkKHRoaXMpLnBhcmVudHMoJy5wYW5lbC1oZWFkaW5nJykubGVuZ3RoKSB7XG4gICAgICAgICAgICAgICAgJCh0aGlzKS5wYXJlbnRzKCcucGFuZWwtaGVhZGluZycpLnNpYmxpbmdzKCcucGFuZWwtYm9keScpLmhpZGUoKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgJCh0aGlzKS5wYXJlbnRzKCcueHQtc2hvdy1oaWRlLS1wYXJlbnQnKS5uZXh0KCcueHQtc2hvdy1oaWRlLS10YXJnZXQnKS5oaWRlKCk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0pO1xuICAgICAgICAkKCcueHQtc2hvdycpLm9uKCdjbGljaycsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgICAgICQodGhpcykuaGlkZSgpO1xuICAgICAgICAgICAgJCh0aGlzKS5zaWJsaW5ncygnLnh0LWhpZGUnKS5zaG93KCk7XG5cbiAgICAgICAgICAgIGlmICgkKHRoaXMpLnBhcmVudHMoJy5wYW5lbC1oZWFkaW5nJykubGVuZ3RoKSB7XG4gICAgICAgICAgICAgICAgJCh0aGlzKS5wYXJlbnRzKCcucGFuZWwtaGVhZGluZycpLnNpYmxpbmdzKCcucGFuZWwtYm9keScpLnNob3coKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgJCh0aGlzKS5wYXJlbnRzKCcueHQtc2hvdy1oaWRlLS1wYXJlbnQnKS5uZXh0KCcueHQtc2hvdy1oaWRlLS10YXJnZXQnKS5zaG93KCk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0pO1xuXG4gICAgICAgIHNldHVwTmF2Q29sbGFwc2luZygpO1xuXG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi5zZXR1cENvbHVtblNvcnRpbmcoKTtcbiAgICAgICAgc2V0dXBUT0MoKTtcbiAgICAgICAgc2V0dXBTdGlja3lIZWFkZXIoKTtcbiAgICAgICAgc2V0dXBQcm9qZWN0TGlzdGVuZXIoKTtcbiAgICAgICAgc2V0dXBBdXRvY29tcGxldGlvbigpO1xuICAgICAgICBkaXNwbGF5V2FpdGluZ05vdGljZU9uU3VibWlzc2lvbigpO1xuICAgICAgICBzZXR1cFBpZUNoYXJ0cygpO1xuXG4gICAgICAgIC8vIEFsbG93IHRvIGFkZCBmb2N1cyB0byBpbnB1dCBlbGVtZW50cyB3aXRoIGkuZS4gP2ZvY3VzPXVzZXJuYW1lXG4gICAgICAgIGlmICgnZnVuY3Rpb24nID09PSB0eXBlb2YgVVJMKSB7XG4gICAgICAgICAgICBjb25zdCBmb2N1c0VsZW1lbnQgPSBuZXcgVVJMKHdpbmRvdy5sb2NhdGlvbi5ocmVmKVxuICAgICAgICAgICAgICAgIC5zZWFyY2hQYXJhbXNcbiAgICAgICAgICAgICAgICAuZ2V0KCdmb2N1cycpO1xuICAgICAgICAgICAgaWYgKGZvY3VzRWxlbWVudCkge1xuICAgICAgICAgICAgICAgICQoYFtuYW1lPSR7Zm9jdXNFbGVtZW50fV1gKS5mb2N1cygpO1xuICAgICAgICAgICAgfVxuICAgICAgICB9XG4gICAgfSk7XG5cbiAgICAvLyBSZS1pbml0IGZvcm1zLCB3b3JrYXJvdW5kIGZvciBpc3N1ZXMgd2l0aCBTYWZhcmkgYW5kIEZpcmVmb3guXG4gICAgLy8gU2VlIGRpc3BsYXlXYWl0aW5nTm90aWNlT25TdWJtaXNzaW9uKCkgZm9yIG1vcmUuXG4gICAgd2luZG93Lm9ucGFnZXNob3cgPSBmdW5jdGlvbiAoZSkge1xuICAgICAgICBpZiAoZS5wZXJzaXN0ZWQpIHtcbiAgICAgICAgICAgIGRpc3BsYXlXYWl0aW5nTm90aWNlT25TdWJtaXNzaW9uKHRydWUpO1xuICAgICAgICB9XG4gICAgfTtcbn0pO1xuXG4vKipcbiAqIFNjcmlwdCB0byBtYWtlIGludGVyYWN0aXZlIHRvZ2dsZSB0YWJsZSBhbmQgcGllIGNoYXJ0LlxuICogRm9yIHZpc3VhbCBleGFtcGxlLCBzZWUgdGhlIFwiU2VtaS1hdXRvbWF0ZWQgZWRpdHNcIiBzZWN0aW9uIG9mIHRoZSBBdXRvRWRpdHMgdG9vbC5cbiAqXG4gKiBFeGFtcGxlIHVzYWdlIChzZWUgYXV0b0VkaXRzL3Jlc3VsdC5odG1sLnR3aWcgYW5kIGpzL2F1dG9lZGl0cy5qcyBmb3IgbW9yZSk6XG4gKiAgICAgPHRhYmxlIGNsYXNzPVwidGFibGUgdGFibGUtYm9yZGVyZWQgdGFibGUtaG92ZXIgdGFibGUtc3RyaXBlZCB0b2dnbGUtdGFibGVcIj5cbiAqICAgICAgICAgPHRoZWFkPi4uLjwvdGhlYWQ+XG4gKiAgICAgICAgIDx0Ym9keT5cbiAqICAgICAgICAgICAgIHslIGZvciB0b29sLCB2YWx1ZXMgaW4gc2VtaV9hdXRvbWF0ZWQgJX1cbiAqICAgICAgICAgICAgIDx0cj5cbiAqICAgICAgICAgICAgICAgICA8IS0tIHVzZSB0aGUgJ2xpbmtlZCcgY2xhc3MgaGVyZSBiZWNhdXNlIHRoZSBjZWxsIGNvbnRhaW5zIGEgbGluayAtLT5cbiAqICAgICAgICAgICAgICAgICA8dGQgY2xhc3M9XCJzb3J0LWVudHJ5LS10b29sIGxpbmtlZFwiIGRhdGEtdmFsdWU9XCJ7eyB0b29sIH19XCI+XG4gKiAgICAgICAgICAgICAgICAgICAgIDxzcGFuIGNsYXNzPVwidG9nZ2xlLXRhYmxlLS10b2dnbGVcIiBkYXRhLWluZGV4PVwie3sgbG9vcC5pbmRleDAgfX1cIiBkYXRhLWtleT1cInt7IHRvb2wgfX1cIj5cbiAqICAgICAgICAgICAgICAgICAgICAgICAgIDxzcGFuIGNsYXNzPVwiZ2x5cGhpY29uIGdseXBoaWNvbi1yZW1vdmVcIj48L3NwYW4+XG4gKiAgICAgICAgICAgICAgICAgICAgICAgICA8c3BhbiBjbGFzcz1cImNvbG9yLWljb25cIiBzdHlsZT1cImJhY2tncm91bmQ6e3sgY2hhcnRDb2xvcihsb29wLmluZGV4MCkgfX1cIj48L3NwYW4+XG4gKiAgICAgICAgICAgICAgICAgICAgIDwvc3Bhbj5cbiAqICAgICAgICAgICAgICAgICAgICAge3sgd2lraS5wYWdlTGluayguLi4pIH19XG4gKiAgICAgICAgICAgICAgICAgPC90ZD5cbiAqICAgICAgICAgICAgICAgICA8dGQgY2xhc3M9XCJzb3J0LWVudHJ5LS1jb3VudFwiIGRhdGEtdmFsdWU9XCJ7eyB2YWx1ZXMuY291bnQgfX1cIj5cbiAqICAgICAgICAgICAgICAgICAgICAge3sgdmFsdWVzLmNvdW50IH19XG4gKiAgICAgICAgICAgICAgICAgPC90ZD5cbiAqICAgICAgICAgICAgIDwvdHI+XG4gKiAgICAgICAgICAgICB7JSBlbmRmb3IgJX1cbiAqICAgICAgICAgICAgIC4uLlxuICogICAgICAgICA8L3Rib2R5PlxuICogICAgIDwvdGFibGU+XG4gKiAgICAgPGRpdiBjbGFzcz1cInRvZ2dsZS10YWJsZS0tY2hhcnRcIj5cbiAqICAgICAgICAgPGNhbnZhcyBpZD1cInRvb2xfY2hhcnRcIiB3aWR0aD1cIjQwMFwiIGhlaWdodD1cIjQwMFwiPjwvY2FudmFzPlxuICogICAgIDwvZGl2PlxuICogICAgIDxzY3JpcHQ+XG4gKiAgICAgICAgIHdpbmRvdy50b29sc0NoYXJ0ID0gbmV3IENoYXJ0KCQoJyN0b29sX2NoYXJ0JyksIHsgLi4uIH0pO1xuICogICAgICAgICB3aW5kb3cuY291bnRzQnlUb29sID0ge3sgc2VtaV9hdXRvbWF0ZWQgfCBqc29uX2VuY29kZSgpIHwgcmF3IH19O1xuICogICAgICAgICAuLi5cbiAqXG4gKiAgICAgICAgIC8vIFNlZSBhdXRvZWRpdHMuanMgZm9yIG1vcmVcbiAqICAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwVG9nZ2xlVGFibGUod2luZG93LmNvdW50c0J5VG9vbCwgd2luZG93LnRvb2xzQ2hhcnQsICdjb3VudCcsIGZ1bmN0aW9uIChuZXdEYXRhKSB7XG4gKiAgICAgICAgICAgICAvLyB1cGRhdGUgdGhlIHRvdGFscyBpbiB0b2dnbGUgdGFibGUgYmFzZWQgb24gbmV3RGF0YVxuICogICAgICAgICB9KTtcbiAqICAgICA8L3NjcmlwdD5cbiAqXG4gKiBAcGFyYW0gIHtPYmplY3R9ICAgICAgZGF0YVNvdXJjZSAgT2JqZWN0IG9mIGRhdGEgdGhhdCBtYWtlcyB1cCB0aGUgY2hhcnRcbiAqIEBwYXJhbSAge0NoYXJ0fSAgICAgICBjaGFydE9iaiAgICBSZWZlcmVuY2UgdG8gdGhlIHBpZSBjaGFydCBhc3NvY2lhdGVkIHdpdGggdGhlIC50b2dnbGUtdGFibGVcbiAqIEBwYXJhbSAge1N0cmluZ3xudWxsfSBbdmFsdWVLZXldICBUaGUgbmFtZSBvZiB0aGUga2V5IHdpdGhpbiBlbnRyaWVzIG9mIGRhdGFTb3VyY2UsIHdoZXJlIHRoZSB2YWx1ZSBpc1xuICogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHdoYXQncyBzaG93biBpbiB0aGUgY2hhcnQuIElmIG9taXR0ZWQgb3IgbnVsbCwgYGRhdGFTb3VyY2VgIGlzIGFzc3VtZWRcbiAqICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0byBiZSBvZiB0aGUgc3RydWN0dXJlOiB7ICdhJyA9PiAxMjMsICdiJyA9PiA0NTYgfVxuICogQHBhcmFtICB7RnVuY3Rpb259IHVwZGF0ZUNhbGxiYWNrIENhbGxiYWNrIHRvIHVwZGF0ZSB0aGUgLnRvZ2dsZS10YWJsZSB0b3RhbHMuIGB0b2dnbGVUYWJsZURhdGFgXG4gKiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgaXMgcGFzc2VkIGluIHdoaWNoIGNvbnRhaW5zIHRoZSBuZXcgZGF0YSwgeW91IGp1c3QgbmVlZCB0b1xuICogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGZvcm1hdCBpdCAobWF5YmUgbmVlZCB0byB1c2UgaTE4biwgdXBkYXRlIG11bHRpcGxlIGNlbGxzLCBldGMuKS5cbiAqICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBUaGUgc2Vjb25kIHBhcmFtZXRlciB0aGF0IGlzIHBhc3NlZCBiYWNrIGlzIHRoZSAna2V5JyBvZiB0aGUgdG9nZ2xlZFxuICogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGl0ZW0sIGFuZCB0aGUgdGhpcmQgaXMgdGhlIGluZGV4IG9mIHRoZSBpdGVtLlxuICovXG54dG9vbHMuYXBwbGljYXRpb24uc2V0dXBUb2dnbGVUYWJsZSA9IGZ1bmN0aW9uIChkYXRhU291cmNlLCBjaGFydE9iaiwgdmFsdWVLZXksIHVwZGF0ZUNhbGxiYWNrKSB7XG4gICAgbGV0IHRvZ2dsZVRhYmxlRGF0YTtcblxuICAgICQoJy50b2dnbGUtdGFibGUnKS5vbignY2xpY2snLCAnLnRvZ2dsZS10YWJsZS0tdG9nZ2xlJywgZnVuY3Rpb24gKCkge1xuICAgICAgICBpZiAoIXRvZ2dsZVRhYmxlRGF0YSkge1xuICAgICAgICAgICAgLy8gbXVzdCBiZSBjbG9uZWRcbiAgICAgICAgICAgIHRvZ2dsZVRhYmxlRGF0YSA9IE9iamVjdC5hc3NpZ24oe30sIGRhdGFTb3VyY2UpO1xuICAgICAgICB9XG5cbiAgICAgICAgY29uc3QgaW5kZXggPSAkKHRoaXMpLmRhdGEoJ2luZGV4JyksXG4gICAgICAgICAgICBrZXkgPSAkKHRoaXMpLmRhdGEoJ2tleScpO1xuXG4gICAgICAgIC8vIG11c3QgdXNlIC5hdHRyIGluc3RlYWQgb2YgLnByb3AgYXMgc29ydGluZyBzY3JpcHQgd2lsbCBjbG9uZSBET00gZWxlbWVudHNcbiAgICAgICAgaWYgKCQodGhpcykuYXR0cignZGF0YS1kaXNhYmxlZCcpID09PSAndHJ1ZScpIHtcbiAgICAgICAgICAgIHRvZ2dsZVRhYmxlRGF0YVtrZXldID0gZGF0YVNvdXJjZVtrZXldO1xuICAgICAgICAgICAgY2hhcnRPYmouZGF0YS5kYXRhc2V0c1swXS5kYXRhW2luZGV4XSA9IChcbiAgICAgICAgICAgICAgICBwYXJzZUludCh2YWx1ZUtleSA/IHRvZ2dsZVRhYmxlRGF0YVtrZXldW3ZhbHVlS2V5XSA6IHRvZ2dsZVRhYmxlRGF0YVtrZXldLCAxMClcbiAgICAgICAgICAgICk7XG4gICAgICAgICAgICAkKHRoaXMpLmF0dHIoJ2RhdGEtZGlzYWJsZWQnLCAnZmFsc2UnKTtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGRlbGV0ZSB0b2dnbGVUYWJsZURhdGFba2V5XTtcbiAgICAgICAgICAgIGNoYXJ0T2JqLmRhdGEuZGF0YXNldHNbMF0uZGF0YVtpbmRleF0gPSBudWxsO1xuICAgICAgICAgICAgJCh0aGlzKS5hdHRyKCdkYXRhLWRpc2FibGVkJywgJ3RydWUnKTtcbiAgICAgICAgfVxuXG4gICAgICAgIC8vIGdyYXkgb3V0IHJvdyBpbiB0YWJsZVxuICAgICAgICAkKHRoaXMpLnBhcmVudHMoJ3RyJykudG9nZ2xlQ2xhc3MoJ2V4Y2x1ZGVkJyk7XG5cbiAgICAgICAgLy8gY2hhbmdlIHRoZSBob3ZlciBpY29uIGZyb20gYSAneCcgdG8gYSAnKydcbiAgICAgICAgJCh0aGlzKS5maW5kKCcuZ2x5cGhpY29uJykudG9nZ2xlQ2xhc3MoJ2dseXBoaWNvbi1yZW1vdmUnKS50b2dnbGVDbGFzcygnZ2x5cGhpY29uLXBsdXMnKTtcblxuICAgICAgICAvLyB1cGRhdGUgc3RhdHNcbiAgICAgICAgdXBkYXRlQ2FsbGJhY2sodG9nZ2xlVGFibGVEYXRhLCBrZXksIGluZGV4KTtcblxuICAgICAgICBjaGFydE9iai51cGRhdGUoKTtcbiAgICB9KTtcbn07XG5cbi8qKlxuICogSWYgdGhlcmUgYXJlIG1vcmUgdG9vbCBsaW5rcyBpbiB0aGUgbmF2IHRoYW4gd2lsbCBmaXQgaW4gdGhlIHZpZXdwb3J0LCBtb3ZlIHRoZSBsYXN0IGVudHJ5IHRvIHRoZSBNb3JlIG1lbnUsXG4gKiBvbmUgYXQgYSB0aW1lLCB1bnRpbCBpdCBhbGwgZml0cy4gVGhpcyBkb2VzIG5vdCBsaXN0ZW4gZm9yIHdpbmRvdyByZXNpemUgZXZlbnRzLlxuICovXG5mdW5jdGlvbiBzZXR1cE5hdkNvbGxhcHNpbmcoKVxue1xuICAgIGxldCB3aW5kb3dXaWR0aCA9ICQod2luZG93KS53aWR0aCgpLFxuICAgICAgICB0b29sTmF2V2lkdGggPSAkKCcudG9vbC1saW5rcycpLm91dGVyV2lkdGgoKSxcbiAgICAgICAgbmF2UmlnaHRXaWR0aCA9ICQoJy5uYXYtYnV0dG9ucycpLm91dGVyV2lkdGgoKTtcblxuICAgIC8vIElnbm9yZSBpZiBpbiBtb2JpbGUgcmVzcG9uc2l2ZSB2aWV3XG4gICAgaWYgKHdpbmRvd1dpZHRoIDwgNzY4KSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICAvLyBEbyB0aGlzIGZpcnN0IHNvIHdlIGFjY291bnQgZm9yIHRoZSBzcGFjZSB0aGUgTW9yZSBtZW51IHRha2VzIHVwXG4gICAgaWYgKHRvb2xOYXZXaWR0aCArIG5hdlJpZ2h0V2lkdGggPiB3aW5kb3dXaWR0aCkge1xuICAgICAgICAkKCcudG9vbC1saW5rcy0tbW9yZScpLnJlbW92ZUNsYXNzKCdoaWRkZW4nKTtcbiAgICB9XG5cbiAgICAvLyBEb24ndCBsb29wIG1vcmUgdGhhbiB0aGVyZSBhcmUgbGlua3MgaW4gdGhlIG5hdi5cbiAgICAvLyBUaGlzIG1vcmUganVzdCBhIHNhZmVndWFyZCBhZ2FpbnN0IGFuIGluZmluaXRlIGxvb3Agc2hvdWxkIHNvbWV0aGluZyBnbyB3cm9uZy5cbiAgICBsZXQgbnVtTGlua3MgPSAkKCcudG9vbC1saW5rcy0tZW50cnknKS5sZW5ndGg7XG4gICAgd2hpbGUgKG51bUxpbmtzID4gMCAmJiB0b29sTmF2V2lkdGggKyBuYXZSaWdodFdpZHRoID4gd2luZG93V2lkdGgpIHtcbiAgICAgICAgLy8gUmVtb3ZlIHRoZSBsYXN0IHRvb2wgbGluayB0aGF0IGlzIG5vdCB0aGUgY3VycmVudCB0b29sIGJlaW5nIHVzZWRcbiAgICAgICAgY29uc3QgJGxpbmsgPSAkKCcudG9vbC1saW5rcy0tbmF2ID4gLnRvb2wtbGlua3MtLWVudHJ5Om5vdCguYWN0aXZlKScpLmxhc3QoKS5yZW1vdmUoKTtcbiAgICAgICAgJCgnLnRvb2wtbGlua3MtLW1vcmUgLmRyb3Bkb3duLW1lbnUnKS5hcHBlbmQoJGxpbmspO1xuICAgICAgICB0b29sTmF2V2lkdGggPSAkKCcudG9vbC1saW5rcycpLm91dGVyV2lkdGgoKTtcbiAgICAgICAgbnVtTGlua3MtLTtcbiAgICB9XG59XG5cbi8qKlxuICogU29ydGluZyBvZiBjb2x1bW5zLlxuICpcbiAqICBFeGFtcGxlIHVzYWdlOlxuICogICB7JSBmb3Iga2V5IGluIFsndXNlcm5hbWUnLCAnZWRpdHMnLCAnbWlub3InLCAnZGF0ZSddICV9XG4gKiAgICAgIDx0aD5cbiAqICAgICAgICAgPHNwYW4gY2xhc3M9XCJzb3J0LWxpbmsgc29ydC1saW5rLS17eyBrZXkgfX1cIiBkYXRhLWNvbHVtbj1cInt7IGtleSB9fVwiPlxuICogICAgICAgICAgICB7eyBtc2coa2V5KSB8IGNhcGl0YWxpemUgfX1cbiAqICAgICAgICAgICAgPHNwYW4gY2xhc3M9XCJnbHlwaGljb24gZ2x5cGhpY29uLXNvcnRcIj48L3NwYW4+XG4gKiAgICAgICAgIDwvc3Bhbj5cbiAqICAgICAgPC90aD5cbiAqICB7JSBlbmRmb3IgJX1cbiAqICAgPHRoIGNsYXNzPVwic29ydC1saW5rXCIgZGF0YS1jb2x1bW49XCJ1c2VybmFtZVwiPlVzZXJuYW1lPC90aD5cbiAqICAgLi4uXG4gKiAgIDx0ZCBjbGFzcz1cInNvcnQtZW50cnktLXVzZXJuYW1lXCIgZGF0YS12YWx1ZT1cInt7IHVzZXJuYW1lIH19XCI+e3sgdXNlcm5hbWUgfX08L3RkPlxuICogICAuLi5cbiAqXG4gKiBEYXRhIHR5cGUgaXMgYXV0b21hdGljYWxseSBkZXRlcm1pbmVkLCB3aXRoIHN1cHBvcnQgZm9yIGludGVnZXIsXG4gKiAgIGZsb2F0cywgYW5kIHN0cmluZ3MsIGluY2x1ZGluZyBkYXRlIHN0cmluZ3MgKGUuZy4gXCIyMDE2LTAxLTAxIDEyOjU5XCIpXG4gKi9cbnh0b29scy5hcHBsaWNhdGlvbi5zZXR1cENvbHVtblNvcnRpbmcgPSBmdW5jdGlvbiAoKSB7XG4gICAgbGV0IHNvcnREaXJlY3Rpb24sIHNvcnRDb2x1bW47XG5cbiAgICAkKCcuc29ydC1saW5rJykub24oJ2NsaWNrJywgZnVuY3Rpb24gKCkge1xuICAgICAgICBzb3J0RGlyZWN0aW9uID0gc29ydENvbHVtbiA9PT0gJCh0aGlzKS5kYXRhKCdjb2x1bW4nKSA/IC1zb3J0RGlyZWN0aW9uIDogMTtcblxuICAgICAgICAkKCcuc29ydC1saW5rIC5nbHlwaGljb24nKS5yZW1vdmVDbGFzcygnZ2x5cGhpY29uLXNvcnQtYnktYWxwaGFiZXQtYWx0IGdseXBoaWNvbi1zb3J0LWJ5LWFscGhhYmV0JykuYWRkQ2xhc3MoJ2dseXBoaWNvbi1zb3J0Jyk7XG4gICAgICAgIGNvbnN0IG5ld1NvcnRDbGFzc05hbWUgPSBzb3J0RGlyZWN0aW9uID09PSAxID8gJ2dseXBoaWNvbi1zb3J0LWJ5LWFscGhhYmV0LWFsdCcgOiAnZ2x5cGhpY29uLXNvcnQtYnktYWxwaGFiZXQnO1xuICAgICAgICAkKHRoaXMpLmZpbmQoJy5nbHlwaGljb24nKS5hZGRDbGFzcyhuZXdTb3J0Q2xhc3NOYW1lKS5yZW1vdmVDbGFzcygnZ2x5cGhpY29uLXNvcnQnKTtcblxuICAgICAgICBzb3J0Q29sdW1uID0gJCh0aGlzKS5kYXRhKCdjb2x1bW4nKTtcbiAgICAgICAgY29uc3QgJHRhYmxlID0gJCh0aGlzKS5wYXJlbnRzKCd0YWJsZScpO1xuICAgICAgICBjb25zdCAkZW50cmllcyA9ICR0YWJsZS5maW5kKCcuc29ydC1lbnRyeS0tJyArIHNvcnRDb2x1bW4pLnBhcmVudCgpO1xuXG4gICAgICAgIGlmICghJGVudHJpZXMubGVuZ3RoKSB7XG4gICAgICAgICAgICByZXR1cm47XG4gICAgICAgIH1cblxuICAgICAgICAkZW50cmllcy5zb3J0KGZ1bmN0aW9uIChhLCBiKSB7XG4gICAgICAgICAgICBsZXQgYmVmb3JlID0gJChhKS5maW5kKCcuc29ydC1lbnRyeS0tJyArIHNvcnRDb2x1bW4pLmRhdGEoJ3ZhbHVlJykgfHwgMCxcbiAgICAgICAgICAgICAgICBhZnRlciA9ICQoYikuZmluZCgnLnNvcnQtZW50cnktLScgKyBzb3J0Q29sdW1uKS5kYXRhKCd2YWx1ZScpIHx8IDA7XG5cbiAgICAgICAgICAgIC8vIENhc3QgbnVtZXJpY2FsIHN0cmluZ3MgaW50byBmbG9hdHMgZm9yIGZhc3RlciBzb3J0aW5nLlxuICAgICAgICAgICAgaWYgKCFpc05hTihiZWZvcmUpKSB7XG4gICAgICAgICAgICAgICAgYmVmb3JlID0gcGFyc2VGbG9hdChiZWZvcmUpIHx8IDA7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICBpZiAoIWlzTmFOKGFmdGVyKSkge1xuICAgICAgICAgICAgICAgIGFmdGVyID0gcGFyc2VGbG9hdChhZnRlcikgfHwgMDtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgaWYgKGJlZm9yZSA8IGFmdGVyKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIHNvcnREaXJlY3Rpb247XG4gICAgICAgICAgICB9IGVsc2UgaWYgKGJlZm9yZSA+IGFmdGVyKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIC1zb3J0RGlyZWN0aW9uO1xuICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICByZXR1cm4gMDtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfSk7XG5cbiAgICAgICAgLy8gUmUtZmlsbCB0aGUgcmFuayBjb2x1bW4sIGlmIGFwcGxpY2FibGUuXG4gICAgICAgIGlmICgkKCcuc29ydC1lbnRyeS0tcmFuaycpLmxlbmd0aCA+IDApIHtcbiAgICAgICAgICAgICQuZWFjaCgkZW50cmllcywgZnVuY3Rpb24gKGluZGV4LCBlbnRyeSkge1xuICAgICAgICAgICAgICAgICQoZW50cnkpLmZpbmQoJy5zb3J0LWVudHJ5LS1yYW5rJykudGV4dChpbmRleCArIDEpO1xuICAgICAgICAgICAgfSk7XG4gICAgICAgIH1cblxuICAgICAgICAkdGFibGUuZmluZCgndGJvZHknKS5odG1sKCRlbnRyaWVzKTtcbiAgICB9KTtcbn07XG5cbi8qKlxuICogRmxvYXRpbmcgdGFibGUgb2YgY29udGVudHMuXG4gKlxuICogRXhhbXBsZSB1c2FnZSAoc2VlIGFydGljbGVJbmZvL3Jlc3VsdC5odG1sLnR3aWcgZm9yIG1vcmUpOlxuICogICAgIDxwIGNsYXNzPVwidGV4dC1jZW50ZXIgeHQtaGVhZGluZy1zdWJ0aXRsZVwiPlxuICogICAgICAgICAuLi5cbiAqICAgICA8L3A+XG4gKiAgICAgPGRpdiBjbGFzcz1cInRleHQtY2VudGVyIHh0LXRvY1wiPlxuICogICAgICAgICB7JSBzZXQgc2VjdGlvbnMgPSBbJ2dlbmVyYWxzdGF0cycsICd1c2VydGFibGUnLCAneWVhcmNvdW50cycsICdtb250aGNvdW50cyddICV9XG4gKiAgICAgICAgIHslIGZvciBzZWN0aW9uIGluIHNlY3Rpb25zICV9XG4gKiAgICAgICAgICAgICA8c3Bhbj5cbiAqICAgICAgICAgICAgICAgICA8YSBocmVmPVwiI3t7IHNlY3Rpb24gfX1cIiBkYXRhLXNlY3Rpb249XCJ7eyBzZWN0aW9uIH19XCI+e3sgbXNnKHNlY3Rpb24pIH19PC9hPlxuICogICAgICAgICAgICAgPC9zcGFuPlxuICogICAgICAgICB7JSBlbmRmb3IgJX1cbiAqICAgICA8L2Rpdj5cbiAqICAgICAuLi5cbiAqICAgICB7JSBzZXQgY29udGVudCAlfVxuICogICAgICAgICAuLi5jb250ZW50IGZvciBnZW5lcmFsIHN0YXRzLi4uXG4gKiAgICAgeyUgZW5kc2V0ICV9XG4gKiAgICAge3sgbGF5b3V0LmNvbnRlbnRfYmxvY2soJ2dlbmVyYWxzdGF0cycsIGNvbnRlbnQpIH19XG4gKiAgICAgLi4uXG4gKi9cbmZ1bmN0aW9uIHNldHVwVE9DKClcbntcbiAgICBjb25zdCAkdG9jID0gJCgnLnh0LXRvYycpO1xuXG4gICAgaWYgKCEkdG9jIHx8ICEkdG9jWzBdKSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy50b2NIZWlnaHQgPSAkdG9jLmhlaWdodCgpO1xuXG4gICAgLy8gbGlzdGVuZXJzIG9uIHRoZSBzZWN0aW9uIGxpbmtzXG4gICAgY29uc3Qgc2V0dXBUb2NMaXN0ZW5lcnMgPSBmdW5jdGlvbiAoKSB7XG4gICAgICAgICQoJy54dC10b2MnKS5maW5kKCdhJykub2ZmKCdjbGljaycpLm9uKCdjbGljaycsIGZ1bmN0aW9uIChlKSB7XG4gICAgICAgICAgICBkb2N1bWVudC5hY3RpdmVFbGVtZW50LmJsdXIoKTtcbiAgICAgICAgICAgIGNvbnN0ICRuZXdTZWN0aW9uID0gJCgnIycgKyAkKGUudGFyZ2V0KS5kYXRhKCdzZWN0aW9uJykpO1xuICAgICAgICAgICAgJCh3aW5kb3cpLnNjcm9sbFRvcCgkbmV3U2VjdGlvbi5vZmZzZXQoKS50b3AgLSB4dG9vbHMuYXBwbGljYXRpb24udmFycy50b2NIZWlnaHQpO1xuXG4gICAgICAgICAgICAkKHRoaXMpLnBhcmVudHMoJy54dC10b2MnKS5maW5kKCdhJykucmVtb3ZlQ2xhc3MoJ2JvbGQnKTtcblxuICAgICAgICAgICAgY3JlYXRlVG9jQ2xvbmUoKTtcbiAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLiR0b2NDbG9uZS5hZGRDbGFzcygnYm9sZCcpO1xuICAgICAgICB9KTtcbiAgICB9O1xuICAgIHh0b29scy5hcHBsaWNhdGlvbi5zZXR1cFRvY0xpc3RlbmVycyA9IHNldHVwVG9jTGlzdGVuZXJzO1xuXG4gICAgLy8gY2xvbmUgdGhlIFRPQyBhbmQgYWRkIHBvc2l0aW9uOmZpeGVkXG4gICAgY29uc3QgY3JlYXRlVG9jQ2xvbmUgPSBmdW5jdGlvbiAoKSB7XG4gICAgICAgIGlmICh4dG9vbHMuYXBwbGljYXRpb24udmFycy4kdG9jQ2xvbmUpIHtcbiAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgfVxuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy4kdG9jQ2xvbmUgPSAkdG9jLmNsb25lKCk7XG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLiR0b2NDbG9uZS5hZGRDbGFzcygnZml4ZWQnKTtcbiAgICAgICAgJHRvYy5hZnRlcih4dG9vbHMuYXBwbGljYXRpb24udmFycy4kdG9jQ2xvbmUpO1xuICAgICAgICBzZXR1cFRvY0xpc3RlbmVycygpO1xuICAgIH07XG5cbiAgICAvLyBidWlsZCBvYmplY3QgY29udGFpbmluZyBvZmZzZXRzIG9mIGVhY2ggc2VjdGlvblxuICAgIHh0b29scy5hcHBsaWNhdGlvbi5idWlsZFNlY3Rpb25PZmZzZXRzID0gZnVuY3Rpb24gKCkge1xuICAgICAgICAkLmVhY2goJHRvYy5maW5kKCdhJyksIGZ1bmN0aW9uIChpbmRleCwgdG9jTWVtYmVyKSB7XG4gICAgICAgICAgICBjb25zdCBpZCA9ICQodG9jTWVtYmVyKS5kYXRhKCdzZWN0aW9uJyk7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5zZWN0aW9uT2Zmc2V0W2lkXSA9ICQoJyMnICsgaWQpLm9mZnNldCgpLnRvcDtcbiAgICAgICAgfSk7XG4gICAgfTtcblxuICAgIC8vIHJlYnVpbGQgc2VjdGlvbiBvZmZzZXRzIHdoZW4gc2VjdGlvbnMgYXJlIHNob3duL2hpZGRlblxuICAgICQoJy54dC1zaG93LCAueHQtaGlkZScpLm9uKCdjbGljaycsIHh0b29scy5hcHBsaWNhdGlvbi5idWlsZFNlY3Rpb25PZmZzZXRzKTtcblxuICAgIHh0b29scy5hcHBsaWNhdGlvbi5idWlsZFNlY3Rpb25PZmZzZXRzKCk7XG4gICAgc2V0dXBUb2NMaXN0ZW5lcnMoKTtcblxuICAgIGNvbnN0IHRvY09mZnNldFRvcCA9ICR0b2Mub2Zmc2V0KCkudG9wO1xuICAgICQod2luZG93KS5vbignc2Nyb2xsLnRvYycsIGZ1bmN0aW9uIChlKSB7XG4gICAgICAgIGNvbnN0IHdpbmRvd09mZnNldCA9ICQoZS50YXJnZXQpLnNjcm9sbFRvcCgpO1xuICAgICAgICBjb25zdCBpblJhbmdlID0gd2luZG93T2Zmc2V0ID4gdG9jT2Zmc2V0VG9wO1xuXG4gICAgICAgIGlmIChpblJhbmdlKSB7XG4gICAgICAgICAgICBpZiAoIXh0b29scy5hcHBsaWNhdGlvbi52YXJzLiR0b2NDbG9uZSkge1xuICAgICAgICAgICAgICAgIGNyZWF0ZVRvY0Nsb25lKCk7XG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIC8vIEJvbGRlbiB0aGUgbGluayBmb3Igd2hpY2hldmVyIHNlY3Rpb24gd2UncmUgaW5cbiAgICAgICAgICAgIGxldCAkYWN0aXZlTWVtYmVyO1xuICAgICAgICAgICAgT2JqZWN0LmtleXMoeHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMuc2VjdGlvbk9mZnNldCkuZm9yRWFjaChmdW5jdGlvbiAoc2VjdGlvbikge1xuICAgICAgICAgICAgICAgIGlmICh3aW5kb3dPZmZzZXQgPiB4dG9vbHMuYXBwbGljYXRpb24udmFycy5zZWN0aW9uT2Zmc2V0W3NlY3Rpb25dIC0geHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMudG9jSGVpZ2h0IC0gMSkge1xuICAgICAgICAgICAgICAgICAgICAkYWN0aXZlTWVtYmVyID0geHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMuJHRvY0Nsb25lLmZpbmQoJ2FbZGF0YS1zZWN0aW9uPVwiJyArIHNlY3Rpb24gKyAnXCJdJyk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy4kdG9jQ2xvbmUuZmluZCgnYScpLnJlbW92ZUNsYXNzKCdib2xkJyk7XG4gICAgICAgICAgICBpZiAoJGFjdGl2ZU1lbWJlcikge1xuICAgICAgICAgICAgICAgICRhY3RpdmVNZW1iZXIuYWRkQ2xhc3MoJ2JvbGQnKTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfSBlbHNlIGlmICghaW5SYW5nZSAmJiB4dG9vbHMuYXBwbGljYXRpb24udmFycy4kdG9jQ2xvbmUpIHtcbiAgICAgICAgICAgIC8vIHJlbW92ZSB0aGUgY2xvbmUgb25jZSB3ZSdyZSBvdXQgb2YgcmFuZ2VcbiAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLiR0b2NDbG9uZS5yZW1vdmUoKTtcbiAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLiR0b2NDbG9uZSA9IG51bGw7XG4gICAgICAgIH1cbiAgICB9KTtcbn1cblxuLyoqXG4gKiBNYWtlIGFueSB0YWJsZXMgd2l0aCB0aGUgY2xhc3MgJ3RhYmxlLXN0aWNreS1oZWFkZXInIGhhdmUgc3RpY2t5IGhlYWRlcnMuXG4gKiBFLmcuIGFzIHlvdSBzY3JvbGwgdGhlIGhlYWRpbmcgcm93IHdpbGwgYmUgZml4ZWQgYXQgdGhlIHRvcCBmb3IgcmVmZXJlbmNlLlxuICovXG5mdW5jdGlvbiBzZXR1cFN0aWNreUhlYWRlcigpXG57XG4gICAgY29uc3QgJGhlYWRlciA9ICQoJy50YWJsZS1zdGlja3ktaGVhZGVyJyk7XG5cbiAgICBpZiAoISRoZWFkZXIgfHwgISRoZWFkZXJbMF0pIHtcbiAgICAgICAgcmV0dXJuO1xuICAgIH1cblxuICAgIGxldCAkaGVhZGVyUm93ID0gJGhlYWRlci5maW5kKCd0aGVhZCB0cicpLmVxKDApLFxuICAgICAgICAkaGVhZGVyQ2xvbmU7XG5cbiAgICAvLyBNYWtlIGEgY2xvbmUgb2YgdGhlIGhlYWRlciB0byBtYWludGFpbiBwbGFjZW1lbnQgb2YgdGhlIG9yaWdpbmFsIGhlYWRlcixcbiAgICAvLyBtYWtpbmcgdGhlIG9yaWdpbmFsIGhlYWRlciB0aGUgc3RpY2t5IG9uZS4gVGhpcyB3YXkgZXZlbnQgbGlzdGVuZXJzIG9uIGl0XG4gICAgLy8gKHN1Y2ggYXMgY29sdW1uIHNvcnRpbmcpIHdpbGwgc3RpbGwgd29yay5cbiAgICBjb25zdCBjbG9uZUhlYWRlciA9IGZ1bmN0aW9uICgpIHtcbiAgICAgICAgaWYgKCRoZWFkZXJDbG9uZSkge1xuICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG5cbiAgICAgICAgJGhlYWRlckNsb25lID0gJGhlYWRlclJvdy5jbG9uZSgpO1xuICAgICAgICAkaGVhZGVyUm93LmFkZENsYXNzKCdzdGlja3ktaGVhZGluZycpO1xuICAgICAgICAkaGVhZGVyUm93LmJlZm9yZSgkaGVhZGVyQ2xvbmUpO1xuXG4gICAgICAgIC8vIEV4cGxpY2l0bHkgc2V0IHdpZHRocyBvZiBlYWNoIGNvbHVtbiwgd2hpY2ggYXJlIGxvc3Qgd2l0aCBwb3NpdGlvbjphYnNvbHV0ZS5cbiAgICAgICAgJGhlYWRlclJvdy5maW5kKCd0aCcpLmVhY2goZnVuY3Rpb24gKGluZGV4KSB7XG4gICAgICAgICAgICAkKHRoaXMpLmNzcygnd2lkdGgnLCAkaGVhZGVyQ2xvbmUuZmluZCgndGgnKS5lcShpbmRleCkub3V0ZXJXaWR0aCgpKTtcbiAgICAgICAgfSk7XG4gICAgICAgICRoZWFkZXJSb3cuY3NzKCd3aWR0aCcsICRoZWFkZXJDbG9uZS5vdXRlcldpZHRoKCkgKyAxKTtcbiAgICB9O1xuXG4gICAgY29uc3QgaGVhZGVyT2Zmc2V0VG9wID0gJGhlYWRlci5vZmZzZXQoKS50b3A7XG4gICAgJCh3aW5kb3cpLm9uKCdzY3JvbGwuc3RpY2t5SGVhZGVyJywgZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgY29uc3Qgd2luZG93T2Zmc2V0ID0gJChlLnRhcmdldCkuc2Nyb2xsVG9wKCk7XG4gICAgICAgIGNvbnN0IGluUmFuZ2UgPSB3aW5kb3dPZmZzZXQgPiBoZWFkZXJPZmZzZXRUb3A7XG5cbiAgICAgICAgaWYgKGluUmFuZ2UgJiYgISRoZWFkZXJDbG9uZSkge1xuICAgICAgICAgICAgY2xvbmVIZWFkZXIoKTtcbiAgICAgICAgfSBlbHNlIGlmICghaW5SYW5nZSAmJiAkaGVhZGVyQ2xvbmUpIHtcbiAgICAgICAgICAgIC8vIFJlbW92ZSB0aGUgY2xvbmUgb25jZSB3ZSdyZSBvdXQgb2YgcmFuZ2UsXG4gICAgICAgICAgICAvLyBhbmQgbWFrZSB0aGUgb3JpZ2luYWwgdW4tc3RpY2t5LlxuICAgICAgICAgICAgJGhlYWRlclJvdy5yZW1vdmVDbGFzcygnc3RpY2t5LWhlYWRpbmcnKTtcbiAgICAgICAgICAgICRoZWFkZXJDbG9uZS5yZW1vdmUoKTtcbiAgICAgICAgICAgICRoZWFkZXJDbG9uZSA9IG51bGw7XG4gICAgICAgIH0gZWxzZSBpZiAoJGhlYWRlckNsb25lKSB7XG4gICAgICAgICAgICAvLyBUaGUgaGVhZGVyIGlzIHBvc2l0aW9uOmFic29sdXRlIHNvIGl0IHdpbGwgZm9sbG93IHdpdGggWCBzY3JvbGxpbmcsXG4gICAgICAgICAgICAvLyBidXQgZm9yIFkgd2UgbXVzdCBnbyBieSB0aGUgd2luZG93IHNjcm9sbCBwb3NpdGlvbi5cbiAgICAgICAgICAgICRoZWFkZXJSb3cuY3NzKFxuICAgICAgICAgICAgICAgICd0b3AnLFxuICAgICAgICAgICAgICAgICQod2luZG93KS5zY3JvbGxUb3AoKSAtICRoZWFkZXIub2Zmc2V0KCkudG9wXG4gICAgICAgICAgICApO1xuICAgICAgICB9XG4gICAgfSk7XG59XG5cbi8qKlxuICogQWRkIGxpc3RlbmVyIHRvIHRoZSBwcm9qZWN0IGlucHV0IGZpZWxkIHRvIHVwZGF0ZSBhbnkgbmFtZXNwYWNlIHNlbGVjdG9ycyBhbmQgYXV0b2NvbXBsZXRpb24gZmllbGRzLlxuICovXG5mdW5jdGlvbiBzZXR1cFByb2plY3RMaXN0ZW5lcigpXG57XG4gICAgY29uc3QgJHByb2plY3RJbnB1dCA9ICQoJyNwcm9qZWN0X2lucHV0Jyk7XG5cbiAgICAvLyBTdG9wIGhlcmUgaWYgdGhlcmUgaXMgbm8gcHJvamVjdCBmaWVsZFxuICAgIGlmICghJHByb2plY3RJbnB1dCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgLy8gSWYgYXBwbGljYWJsZSwgc2V0dXAgbmFtZXNwYWNlIHNlbGVjdG9yIHdpdGggcmVhbCB0aW1lIHVwZGF0ZXMgd2hlbiBjaGFuZ2luZyBwcm9qZWN0cy5cbiAgICAvLyBUaGlzIHdpbGwgYWxzbyBzZXQgYGFwaVBhdGhgIHNvIHRoYXQgYXV0b2NvbXBsZXRpb24gd2lsbCBxdWVyeSB0aGUgcmlnaHQgd2lraS5cbiAgICBpZiAoJHByb2plY3RJbnB1dC5sZW5ndGggJiYgJCgnI25hbWVzcGFjZV9zZWxlY3QnKS5sZW5ndGgpIHtcbiAgICAgICAgc2V0dXBOYW1lc3BhY2VTZWxlY3RvcigpO1xuICAgICAgICAvLyBPdGhlcndpc2UsIGlmIHRoZXJlJ3MgYSB1c2VyIG9yIHBhZ2UgaW5wdXQgZmllbGQsIHdlIHN0aWxsIG5lZWQgdG8gdXBkYXRlIGBhcGlQYXRoYFxuICAgICAgICAvLyBmb3IgdGhlIHVzZXIgaW5wdXQgYXV0b2NvbXBsZXRpb24gd2hlbiB0aGUgcHJvamVjdCBpcyBjaGFuZ2VkLlxuICAgIH0gZWxzZSBpZiAoJCgnI3VzZXJfaW5wdXQnKVswXSB8fCAkKCcjYXJ0aWNsZV9pbnB1dCcpWzBdKSB7XG4gICAgICAgIC8vIGtlZXAgdHJhY2sgb2YgbGFzdCB2YWxpZCBwcm9qZWN0XG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLmxhc3RQcm9qZWN0ID0gJHByb2plY3RJbnB1dC52YWwoKTtcblxuICAgICAgICAkcHJvamVjdElucHV0Lm9uKCdjaGFuZ2UnLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICBjb25zdCBuZXdQcm9qZWN0ID0gdGhpcy52YWx1ZTtcblxuICAgICAgICAgICAgLyoqIGdsb2JhbDogeHRCYXNlVXJsICovXG4gICAgICAgICAgICAkLmdldCh4dEJhc2VVcmwgKyAnYXBpL3Byb2plY3Qvbm9ybWFsaXplLycgKyBuZXdQcm9qZWN0KS5kb25lKGZ1bmN0aW9uIChkYXRhKSB7XG4gICAgICAgICAgICAgICAgLy8gS2VlcCB0cmFjayBvZiBwcm9qZWN0IEFQSSBwYXRoIGZvciB1c2UgaW4gcGFnZSB0aXRsZSBhdXRvY29tcGxldGlvblxuICAgICAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLmFwaVBhdGggPSBkYXRhLmFwaTtcbiAgICAgICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5sYXN0UHJvamVjdCA9IG5ld1Byb2plY3Q7XG4gICAgICAgICAgICAgICAgc2V0dXBBdXRvY29tcGxldGlvbigpO1xuXG4gICAgICAgICAgICAgICAgLy8gT3RoZXIgcGFnZXMgbWF5IGxpc3RlbiBmb3IgdGhpcyBjdXN0b20gZXZlbnQuXG4gICAgICAgICAgICAgICAgJHByb2plY3RJbnB1dC50cmlnZ2VyKCd4dG9vbHMucHJvamVjdExvYWRlZCcsIGRhdGEpO1xuICAgICAgICAgICAgfSkuZmFpbChcbiAgICAgICAgICAgICAgICByZXZlcnRUb1ZhbGlkUHJvamVjdC5iaW5kKHRoaXMsIG5ld1Byb2plY3QpXG4gICAgICAgICAgICApO1xuICAgICAgICB9KTtcbiAgICB9XG59XG5cbi8qKlxuICogVXNlIHRoZSB3aWtpIGlucHV0IGZpZWxkIHRvIHBvcHVsYXRlIHRoZSBuYW1lc3BhY2Ugc2VsZWN0b3IuXG4gKiBUaGlzIGFsc28gdXBkYXRlcyBgYXBpUGF0aGAgYW5kIGNhbGxzIHNldHVwQXV0b2NvbXBsZXRpb24oKS5cbiAqL1xuZnVuY3Rpb24gc2V0dXBOYW1lc3BhY2VTZWxlY3RvcigpXG57XG4gICAgLy8ga2VlcCB0cmFjayBvZiBsYXN0IHZhbGlkIHByb2plY3RcbiAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5sYXN0UHJvamVjdCA9ICQoJyNwcm9qZWN0X2lucHV0JykudmFsKCk7XG5cbiAgICAkKCcjcHJvamVjdF9pbnB1dCcpLm9mZignY2hhbmdlJykub24oJ2NoYW5nZScsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgLy8gRGlzYWJsZSB0aGUgbmFtZXNwYWNlIHNlbGVjdG9yIGFuZCBzaG93IGEgc3Bpbm5lciB3aGlsZSB0aGUgZGF0YSBsb2Fkcy5cbiAgICAgICAgJCgnI25hbWVzcGFjZV9zZWxlY3QnKS5wcm9wKCdkaXNhYmxlZCcsIHRydWUpO1xuXG4gICAgICAgIGNvbnN0IG5ld1Byb2plY3QgPSB0aGlzLnZhbHVlO1xuXG4gICAgICAgIC8qKiBnbG9iYWw6IHh0QmFzZVVybCAqL1xuICAgICAgICAkLmdldCh4dEJhc2VVcmwgKyAnYXBpL3Byb2plY3QvbmFtZXNwYWNlcy8nICsgbmV3UHJvamVjdCkuZG9uZShmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgLy8gQ2xvbmUgdGhlICdhbGwnIG9wdGlvbiAoZXZlbiBpZiB0aGVyZSBpc24ndCBvbmUpLFxuICAgICAgICAgICAgLy8gYW5kIHJlcGxhY2UgdGhlIGN1cnJlbnQgb3B0aW9uIGxpc3Qgd2l0aCB0aGlzLlxuICAgICAgICAgICAgY29uc3QgJGFsbE9wdGlvbiA9ICQoJyNuYW1lc3BhY2Vfc2VsZWN0IG9wdGlvblt2YWx1ZT1cImFsbFwiXScpLmVxKDApLmNsb25lKCk7XG4gICAgICAgICAgICAkKFwiI25hbWVzcGFjZV9zZWxlY3RcIikuaHRtbCgkYWxsT3B0aW9uKTtcblxuICAgICAgICAgICAgLy8gS2VlcCB0cmFjayBvZiBwcm9qZWN0IEFQSSBwYXRoIGZvciB1c2UgaW4gcGFnZSB0aXRsZSBhdXRvY29tcGxldGlvbi5cbiAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLmFwaVBhdGggPSBkYXRhLmFwaTtcblxuICAgICAgICAgICAgLy8gQWRkIGFsbCBvZiB0aGUgbmV3IG5hbWVzcGFjZSBvcHRpb25zLlxuICAgICAgICAgICAgZm9yIChjb25zdCBucyBpbiBkYXRhLm5hbWVzcGFjZXMpIHtcbiAgICAgICAgICAgICAgICBpZiAoIWRhdGEubmFtZXNwYWNlcy5oYXNPd25Qcm9wZXJ0eShucykpIHtcbiAgICAgICAgICAgICAgICAgICAgY29udGludWU7IC8vIFNraXAga2V5cyBmcm9tIHRoZSBwcm90b3R5cGUuXG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgY29uc3QgbnNOYW1lID0gcGFyc2VJbnQobnMsIDEwKSA9PT0gMCA/ICQuaTE4bignbWFpbnNwYWNlJykgOiBkYXRhLm5hbWVzcGFjZXNbbnNdO1xuICAgICAgICAgICAgICAgICQoJyNuYW1lc3BhY2Vfc2VsZWN0JykuYXBwZW5kKFxuICAgICAgICAgICAgICAgICAgICBcIjxvcHRpb24gdmFsdWU9XCIgKyBucyArIFwiPlwiICsgbnNOYW1lICsgXCI8L29wdGlvbj5cIlxuICAgICAgICAgICAgICAgICk7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICAvLyBEZWZhdWx0IHRvIG1haW5zcGFjZSBiZWluZyBzZWxlY3RlZC5cbiAgICAgICAgICAgICQoXCIjbmFtZXNwYWNlX3NlbGVjdFwiKS52YWwoMCk7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5sYXN0UHJvamVjdCA9IG5ld1Byb2plY3Q7XG5cbiAgICAgICAgICAgIC8vIFJlLWluaXQgYXV0b2NvbXBsZXRpb25cbiAgICAgICAgICAgIHNldHVwQXV0b2NvbXBsZXRpb24oKTtcbiAgICAgICAgfSkuZmFpbChyZXZlcnRUb1ZhbGlkUHJvamVjdC5iaW5kKHRoaXMsIG5ld1Byb2plY3QpKS5hbHdheXMoZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgJCgnI25hbWVzcGFjZV9zZWxlY3QnKS5wcm9wKCdkaXNhYmxlZCcsIGZhbHNlKTtcbiAgICAgICAgfSk7XG4gICAgfSk7XG5cbiAgICAvLyBJZiB0aGV5IGNoYW5nZSB0aGUgbmFtZXNwYWNlLCB1cGRhdGUgYXV0b2NvbXBsZXRpb24sXG4gICAgLy8gd2hpY2ggd2lsbCBlbnN1cmUgb25seSBwYWdlcyBpbiB0aGUgc2VsZWN0ZWQgbmFtZXNwYWNlXG4gICAgLy8gc2hvdyB1cCBpbiB0aGUgYXV0b2NvbXBsZXRpb25cbiAgICAkKCcjbmFtZXNwYWNlX3NlbGVjdCcpLm9uKCdjaGFuZ2UnLCBzZXR1cEF1dG9jb21wbGV0aW9uKTtcbn1cblxuLyoqXG4gKiBDYWxsZWQgYnkgc2V0dXBOYW1lc3BhY2VTZWxlY3RvciBvciBzZXR1cFByb2plY3RMaXN0ZW5lciB3aGVuIHRoZSB1c2VyIGNoYW5nZXMgdG8gYSBwcm9qZWN0IHRoYXQgZG9lc24ndCBleGlzdC5cbiAqIFRoaXMgdGhyb3dzIGEgd2FybmluZyBtZXNzYWdlIGFuZCByZXZlcnRzIGJhY2sgdG8gdGhlIGxhc3QgdmFsaWQgcHJvamVjdC5cbiAqIEBwYXJhbSB7c3RyaW5nfSBuZXdQcm9qZWN0IC0gcHJvamVjdCB0aGV5IGF0dGVtcHRlZCB0byBhZGRcbiAqL1xuZnVuY3Rpb24gcmV2ZXJ0VG9WYWxpZFByb2plY3QobmV3UHJvamVjdClcbntcbiAgICAkKCcjcHJvamVjdF9pbnB1dCcpLnZhbCh4dG9vbHMuYXBwbGljYXRpb24udmFycy5sYXN0UHJvamVjdCk7XG4gICAgJCgnLnNpdGUtbm90aWNlJykuYXBwZW5kKFxuICAgICAgICBcIjxkaXYgY2xhc3M9J2FsZXJ0IGFsZXJ0LXdhcm5pbmcgYWxlcnQtZGlzbWlzc2libGUnIHJvbGU9J2FsZXJ0Jz5cIiArXG4gICAgICAgICQuaTE4bignaW52YWxpZC1wcm9qZWN0JywgXCI8c3Ryb25nPlwiICsgbmV3UHJvamVjdCArIFwiPC9zdHJvbmc+XCIpICtcbiAgICAgICAgXCI8YnV0dG9uIGNsYXNzPSdjbG9zZScgZGF0YS1kaXNtaXNzPSdhbGVydCcgYXJpYS1sYWJlbD0nQ2xvc2UnPlwiICtcbiAgICAgICAgXCI8c3BhbiBhcmlhLWhpZGRlbj0ndHJ1ZSc+JnRpbWVzOzwvc3Bhbj5cIiArXG4gICAgICAgIFwiPC9idXR0b24+XCIgK1xuICAgICAgICBcIjwvZGl2PlwiXG4gICAgKTtcbn1cblxuLyoqXG4gKiBTZXR1cCBhdXRvY29tcGxldGlvbiBvZiBwYWdlcyBpZiBhIHBhZ2UgaW5wdXQgZmllbGQgaXMgcHJlc2VudC5cbiAqL1xuZnVuY3Rpb24gc2V0dXBBdXRvY29tcGxldGlvbigpXG57XG4gICAgY29uc3QgJGFydGljbGVJbnB1dCA9ICQoJyNhcnRpY2xlX2lucHV0JyksXG4gICAgICAgICR1c2VySW5wdXQgPSAkKCcjdXNlcl9pbnB1dCcpLFxuICAgICAgICAkbmFtZXNwYWNlSW5wdXQgPSAkKFwiI25hbWVzcGFjZV9zZWxlY3RcIik7XG5cbiAgICAvLyBNYWtlIHN1cmUgdHlwZWFoZWFkLWNvbXBhdGlibGUgZmllbGRzIGFyZSBwcmVzZW50XG4gICAgaWYgKCEkYXJ0aWNsZUlucHV0WzBdICYmICEkdXNlcklucHV0WzBdICYmICEkKCcjcHJvamVjdF9pbnB1dCcpWzBdKSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICAvLyBEZXN0cm95IGFueSBleGlzdGluZyBpbnN0YW5jZXNcbiAgICBpZiAoJGFydGljbGVJbnB1dC5kYXRhKCd0eXBlYWhlYWQnKSkge1xuICAgICAgICAkYXJ0aWNsZUlucHV0LmRhdGEoJ3R5cGVhaGVhZCcpLmRlc3Ryb3koKTtcbiAgICB9XG4gICAgaWYgKCR1c2VySW5wdXQuZGF0YSgndHlwZWFoZWFkJykpIHtcbiAgICAgICAgJHVzZXJJbnB1dC5kYXRhKCd0eXBlYWhlYWQnKS5kZXN0cm95KCk7XG4gICAgfVxuXG4gICAgLy8gc2V0IGluaXRpYWwgdmFsdWUgZm9yIHRoZSBBUEkgdXJsLCB3aGljaCBpcyBwdXQgYXMgYSBkYXRhIGF0dHJpYnV0ZSBpbiBmb3Jtcy5odG1sLnR3aWdcbiAgICBpZiAoIXh0b29scy5hcHBsaWNhdGlvbi52YXJzLmFwaVBhdGgpIHtcbiAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMuYXBpUGF0aCA9ICQoJyNhcnRpY2xlX2lucHV0JykuZGF0YSgnYXBpJykgfHwgJCgnI3VzZXJfaW5wdXQnKS5kYXRhKCdhcGknKTtcbiAgICB9XG5cbiAgICAvLyBEZWZhdWx0cyBmb3IgdHlwZWFoZWFkIG9wdGlvbnMuIHByZURpc3BhdGNoIGFuZCBwcmVQcm9jZXNzIHdpbGwgYmVcbiAgICAvLyBzZXQgYWNjb3JkaW5nbHkgZm9yIGVhY2ggdHlwZWFoZWFkIGluc3RhbmNlXG4gICAgY29uc3QgdHlwZWFoZWFkT3B0cyA9IHtcbiAgICAgICAgdXJsOiB4dG9vbHMuYXBwbGljYXRpb24udmFycy5hcGlQYXRoLFxuICAgICAgICB0aW1lb3V0OiAyMDAsXG4gICAgICAgIHRyaWdnZXJMZW5ndGg6IDEsXG4gICAgICAgIG1ldGhvZDogJ2dldCcsXG4gICAgICAgIHByZURpc3BhdGNoOiBudWxsLFxuICAgICAgICBwcmVQcm9jZXNzOiBudWxsXG4gICAgfTtcblxuICAgIGlmICgkYXJ0aWNsZUlucHV0WzBdKSB7XG4gICAgICAgICRhcnRpY2xlSW5wdXQudHlwZWFoZWFkKHtcbiAgICAgICAgICAgIGFqYXg6IE9iamVjdC5hc3NpZ24odHlwZWFoZWFkT3B0cywge1xuICAgICAgICAgICAgICAgIHByZURpc3BhdGNoOiBmdW5jdGlvbiAocXVlcnkpIHtcbiAgICAgICAgICAgICAgICAgICAgLy8gSWYgdGhlcmUgaXMgYSBuYW1lc3BhY2Ugc2VsZWN0b3IsIG1ha2Ugc3VyZSB3ZSBzZWFyY2hcbiAgICAgICAgICAgICAgICAgICAgLy8gb25seSB3aXRoaW4gdGhhdCBuYW1lc3BhY2VcbiAgICAgICAgICAgICAgICAgICAgaWYgKCRuYW1lc3BhY2VJbnB1dFswXSAmJiAkbmFtZXNwYWNlSW5wdXQudmFsKCkgIT09ICcwJykge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29uc3QgbnNOYW1lID0gJG5hbWVzcGFjZUlucHV0LmZpbmQoJ29wdGlvbjpzZWxlY3RlZCcpLnRleHQoKS50cmltKCk7XG4gICAgICAgICAgICAgICAgICAgICAgICBxdWVyeSA9IG5zTmFtZSArICc6JyArIHF1ZXJ5O1xuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB7XG4gICAgICAgICAgICAgICAgICAgICAgICBhY3Rpb246ICdxdWVyeScsXG4gICAgICAgICAgICAgICAgICAgICAgICBsaXN0OiAncHJlZml4c2VhcmNoJyxcbiAgICAgICAgICAgICAgICAgICAgICAgIGZvcm1hdDogJ2pzb24nLFxuICAgICAgICAgICAgICAgICAgICAgICAgcHNzZWFyY2g6IHF1ZXJ5XG4gICAgICAgICAgICAgICAgICAgIH07XG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICBwcmVQcm9jZXNzOiBmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgICAgICAgICBsZXQgbnNOYW1lID0gJyc7XG4gICAgICAgICAgICAgICAgICAgIC8vIFN0cmlwIG91dCBuYW1lc3BhY2UgbmFtZSBpZiBhcHBsaWNhYmxlXG4gICAgICAgICAgICAgICAgICAgIGlmICgkbmFtZXNwYWNlSW5wdXRbMF0gJiYgJG5hbWVzcGFjZUlucHV0LnZhbCgpICE9PSAnMCcpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIG5zTmFtZSA9ICRuYW1lc3BhY2VJbnB1dC5maW5kKCdvcHRpb246c2VsZWN0ZWQnKS50ZXh0KCkudHJpbSgpO1xuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBkYXRhLnF1ZXJ5LnByZWZpeHNlYXJjaC5tYXAoZnVuY3Rpb24gKGVsZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBlbGVtLnRpdGxlLnJlcGxhY2UobmV3IFJlZ0V4cCgnXicgKyBuc05hbWUgKyAnOicpLCAnJyk7XG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH0pXG4gICAgICAgIH0pO1xuICAgIH1cblxuICAgIGlmICgkdXNlcklucHV0WzBdKSB7XG4gICAgICAgICR1c2VySW5wdXQudHlwZWFoZWFkKHtcbiAgICAgICAgICAgIGFqYXg6IE9iamVjdC5hc3NpZ24odHlwZWFoZWFkT3B0cywge1xuICAgICAgICAgICAgICAgIHByZURpc3BhdGNoOiBmdW5jdGlvbiAocXVlcnkpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGFjdGlvbjogJ3F1ZXJ5JyxcbiAgICAgICAgICAgICAgICAgICAgICAgIGxpc3Q6ICdwcmVmaXhzZWFyY2gnLFxuICAgICAgICAgICAgICAgICAgICAgICAgZm9ybWF0OiAnanNvbicsXG4gICAgICAgICAgICAgICAgICAgICAgICBwc3NlYXJjaDogJ1VzZXI6JyArIHF1ZXJ5XG4gICAgICAgICAgICAgICAgICAgIH07XG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICBwcmVQcm9jZXNzOiBmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCByZXN1bHRzID0gZGF0YS5xdWVyeS5wcmVmaXhzZWFyY2gubWFwKGZ1bmN0aW9uIChlbGVtKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gZWxlbS50aXRsZS5zcGxpdCgnLycpWzBdLnN1YnN0cihlbGVtLnRpdGxlLmluZGV4T2YoJzonKSArIDEpO1xuICAgICAgICAgICAgICAgICAgICB9KTtcblxuICAgICAgICAgICAgICAgICAgICByZXR1cm4gcmVzdWx0cy5maWx0ZXIoZnVuY3Rpb24gKHZhbHVlLCBpbmRleCwgYXJyYXkpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBhcnJheS5pbmRleE9mKHZhbHVlKSA9PT0gaW5kZXg7XG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH0pXG4gICAgICAgIH0pO1xuICAgIH1cbn1cblxuLyoqXG4gKiBGb3IgYW55IGZvcm0gc3VibWlzc2lvbiwgdGhpcyBkaXNhYmxlcyB0aGUgc3VibWl0IGJ1dHRvbiBhbmQgcmVwbGFjZXMgaXRzIHRleHQgd2l0aFxuICogYSBsb2FkaW5nIG1lc3NhZ2UgYW5kIGEgY291bnRpbmcgdGltZXIuXG4gKiBAcGFyYW0ge2Jvb2xlYW59IFt1bmRvXSBSZXZlcnQgdGhlIGZvcm0gYmFjayB0byB0aGUgaW5pdGlhbCBzdGF0ZS5cbiAqICAgICAgICAgICAgICAgICAgICAgICAgIFRoaXMgaXMgdXNlZCBvbiBwYWdlIGxvYWQgdG8gc29sdmUgYW4gaXNzdWUgd2l0aCBTYWZhcmkgYW5kIEZpcmVmb3hcbiAqICAgICAgICAgICAgICAgICAgICAgICAgIHdoZXJlIGFmdGVyIGJyb3dzaW5nIGJhY2sgdG8gdGhlIGZvcm0sIHRoZSBcImxvYWRpbmdcIiBzdGF0ZSBwZXJzaXN0cy5cbiAqL1xuZnVuY3Rpb24gZGlzcGxheVdhaXRpbmdOb3RpY2VPblN1Ym1pc3Npb24odW5kbylcbntcbiAgICBpZiAodW5kbykge1xuICAgICAgICAvLyBSZS1lbmFibGUgZm9ybVxuICAgICAgICAkKCcuZm9ybS1jb250cm9sJykucHJvcCgncmVhZG9ubHknLCBmYWxzZSk7XG4gICAgICAgICQoJy5mb3JtLXN1Ym1pdCcpLnByb3AoJ2Rpc2FibGVkJywgZmFsc2UpO1xuICAgICAgICAkKCcuZm9ybS1zdWJtaXQnKS50ZXh0KCQuaTE4bignc3VibWl0JykpLnByb3AoJ2Rpc2FibGVkJywgZmFsc2UpO1xuICAgIH0gZWxzZSB7XG4gICAgICAgICQoJyNjb250ZW50IGZvcm0nKS5vbignc3VibWl0JywgZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgLy8gUmVtb3ZlIGZvY3VzIGZyb20gYW55IGFjdGl2ZSBlbGVtZW50XG4gICAgICAgICAgICBkb2N1bWVudC5hY3RpdmVFbGVtZW50LmJsdXIoKTtcblxuICAgICAgICAgICAgLy8gRGlzYWJsZSB0aGUgZm9ybSBzbyB0aGV5IGNhbid0IGhpdCBFbnRlciB0byByZS1zdWJtaXRcbiAgICAgICAgICAgICQoJy5mb3JtLWNvbnRyb2wnKS5wcm9wKCdyZWFkb25seScsIHRydWUpO1xuXG4gICAgICAgICAgICAvLyBDaGFuZ2UgdGhlIHN1Ym1pdCBidXR0b24gdGV4dC5cbiAgICAgICAgICAgICQoJy5mb3JtLXN1Ym1pdCcpLnByb3AoJ2Rpc2FibGVkJywgdHJ1ZSlcbiAgICAgICAgICAgICAgICAuaHRtbCgkLmkxOG4oJ2xvYWRpbmcnKSArIFwiIDxzcGFuIGlkPSdzdWJtaXRfdGltZXInPjwvc3Bhbj5cIik7XG5cbiAgICAgICAgICAgIC8vIEFkZCB0aGUgY291bnRlci5cbiAgICAgICAgICAgIGNvbnN0IHN0YXJ0VGltZSA9IERhdGUubm93KCk7XG4gICAgICAgICAgICBzZXRJbnRlcnZhbChmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICAgICAgY29uc3QgZWxhcHNlZFNlY29uZHMgPSBNYXRoLnJvdW5kKChEYXRlLm5vdygpIC0gc3RhcnRUaW1lKSAvIDEwMDApO1xuICAgICAgICAgICAgICAgIGNvbnN0IG1pbnV0ZXMgPSBNYXRoLmZsb29yKGVsYXBzZWRTZWNvbmRzIC8gNjApO1xuICAgICAgICAgICAgICAgIGNvbnN0IHNlY29uZHMgPSAoJzAwJyArIChlbGFwc2VkU2Vjb25kcyAtIChtaW51dGVzICogNjApKSkuc2xpY2UoLTIpO1xuICAgICAgICAgICAgICAgICQoJyNzdWJtaXRfdGltZXInKS50ZXh0KG1pbnV0ZXMgKyBcIjpcIiArIHNlY29uZHMpO1xuICAgICAgICAgICAgfSwgMTAwMCk7XG4gICAgICAgIH0pO1xuICAgIH1cbn1cblxuZnVuY3Rpb24gc2V0dXBQaWVDaGFydHMoKVxue1xuICAgIGNvbnN0ICRjaGFydHMgPSAkKCcueHQtcGllLWNoYXJ0Jyk7XG5cbn1cblxuLyoqXG4gKiBIYW5kbGVzIHRoZSBtdWx0aS1zZWxlY3QgaW5wdXRzIG9uIHNvbWUgaW5kZXggcGFnZXMuXG4gKi9cbnh0b29scy5hcHBsaWNhdGlvbi5zZXR1cE11bHRpU2VsZWN0TGlzdGVuZXJzID0gZnVuY3Rpb24gKCkge1xuICAgIGNvbnN0ICRpbnB1dHMgPSAkKCcubXVsdGktc2VsZWN0LS1ib2R5Om5vdCguaGlkZGVuKSAubXVsdGktc2VsZWN0LS1vcHRpb24nKTtcbiAgICAkaW5wdXRzLm9uKCdjaGFuZ2UnLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgIC8vIElmIGFsbCBzZWN0aW9ucyBhcmUgc2VsZWN0ZWQsIHNlbGVjdCB0aGUgJ0FsbCcgY2hlY2tib3gsIGFuZCB2aWNlIHZlcnNhLlxuICAgICAgICAkKCcubXVsdGktc2VsZWN0LS1hbGwnKS5wcm9wKFxuICAgICAgICAgICAgJ2NoZWNrZWQnLFxuICAgICAgICAgICAgJCgnLm11bHRpLXNlbGVjdC0tYm9keTpub3QoLmhpZGRlbikgLm11bHRpLXNlbGVjdC0tb3B0aW9uOmNoZWNrZWQnKS5sZW5ndGggPT09ICRpbnB1dHMubGVuZ3RoXG4gICAgICAgICk7XG4gICAgfSk7XG4gICAgLy8gVW5jaGVjay9jaGVjayBhbGwgd2hlbiB0aGUgJ0FsbCcgY2hlY2tib3ggaXMgbW9kaWZpZWQuXG4gICAgJCgnLm11bHRpLXNlbGVjdC0tYWxsJykub24oJ2NsaWNrJywgZnVuY3Rpb24gKCkge1xuICAgICAgICAkaW5wdXRzLnByb3AoJ2NoZWNrZWQnLCAkKHRoaXMpLnByb3AoJ2NoZWNrZWQnKSk7XG4gICAgfSk7XG59O1xuIiwiT2JqZWN0LmFzc2lnbih4dG9vbHMuYXBwbGljYXRpb24udmFycywge1xuICAgIGluaXRpYWxPZmZzZXQ6ICcnLFxuICAgIG9mZnNldDogJycsXG4gICAgcHJldk9mZnNldHM6IFtdLFxuICAgIGluaXRpYWxMb2FkOiBmYWxzZSxcbn0pO1xuXG4vKipcbiAqIFNldCB0aGUgaW5pdGlhbCBvZmZzZXQgZm9yIGNvbnRyaWJ1dGlvbnMgbGlzdHMsIGJhc2VkIG9uIHdoYXQgd2FzXG4gKiBzdXBwbGllZCBpbiB0aGUgY29udHJpYnV0aW9ucyBjb250YWluZXIuXG4gKi9cbmZ1bmN0aW9uIHNldEluaXRpYWxPZmZzZXQoKVxue1xuICAgIGlmICgheHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMub2Zmc2V0KSB7XG4gICAgICAgIC8vIFRoZSBpbml0aWFsT2Zmc2V0IHNob3VsZCBiZSB3aGF0IHdhcyBnaXZlbiB2aWEgdGhlIC5jb250cmlidXRpb25zLWNvbnRhaW5lci5cbiAgICAgICAgLy8gVGhpcyBpcyB1c2VkIHRvIGRldGVybWluZSBpZiB3ZSdyZSBiYWNrIG9uIHRoZSBmaXJzdCBwYWdlIG9yIG5vdC5cbiAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMuaW5pdGlhbE9mZnNldCA9ICQoJy5jb250cmlidXRpb25zLWNvbnRhaW5lcicpLmRhdGEoJ29mZnNldCcpO1xuICAgICAgICAvLyBUaGUgb2Zmc2V0IHdpbGwgZnJvbSBoZXJlIHJlcHJlc2VudCB3aGljaCBwYWdlIHdlJ3JlIG9uLCBhbmQgaXMgY29tcGFyZWQgd2l0aFxuICAgICAgICAvLyBpbnRpdGlhbEVkaXRPZmZzZXQgdG8ga25vdyBpZiB3ZSdyZSBvbiB0aGUgZmlyc3QgcGFnZS5cbiAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMub2Zmc2V0ID0geHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMuaW5pdGlhbE9mZnNldDtcbiAgICB9XG59XG5cbi8qKlxuICogTG9hZHMgY29uZmlndXJlZCB0eXBlIG9mIGNvbnRyaWJ1dGlvbnMgZnJvbSB0aGUgc2VydmVyIGFuZCBsaXN0cyB0aGVtIGluIHRoZSBET00uXG4gKiBUaGUgbmF2aWdhdGlvbiBhaWRzIGFuZCBzaG93aW5nL2hpZGluZyBvZiBsb2FkaW5nIHRleHQgaXMgYWxzbyBoYW5kbGVkIGhlcmUuXG4gKiBAcGFyYW0ge2Z1bmN0aW9ufSBlbmRwb2ludEZ1bmMgVGhlIGNhbGxiYWNrIHRoYXQgdGFrZXMgdGhlIHBhcmFtcyBzZXQgb24gLmNvbnRyaWJ1dGlvbnMtY29udGFpbmVyXG4gKiAgICAgYW5kIHJldHVybnMgYSBzdHJpbmcgdGhhdCBpcyB0aGUgZW5kcG9pbnQgdG8gZmV0Y2ggZnJvbSAod2l0aG91dCB0aGUgb2Zmc2V0IGFwcGVuZGVkKS5cbiAqIEBwYXJhbSB7U3RyaW5nfSBhcGlUaXRsZSBUaGUgbmFtZSBvZiB0aGUgQVBJIChjb3VsZCBiZSBpMThuIGtleSksIHVzZWQgaW4gZXJyb3IgcmVwb3J0aW5nLlxuICovXG54dG9vbHMuYXBwbGljYXRpb24ubG9hZENvbnRyaWJ1dGlvbnMgPSBmdW5jdGlvbiAoZW5kcG9pbnRGdW5jLCBhcGlUaXRsZSkge1xuICAgIHNldEluaXRpYWxPZmZzZXQoKTtcblxuICAgIHZhciAkY29udHJpYnV0aW9uc0NvbnRhaW5lciA9ICQoJy5jb250cmlidXRpb25zLWNvbnRhaW5lcicpLFxuICAgICAgICAkY29udHJpYnV0aW9uc0xvYWRpbmcgPSAkKCcuY29udHJpYnV0aW9ucy1sb2FkaW5nJyksXG4gICAgICAgIHBhcmFtcyA9ICRjb250cmlidXRpb25zQ29udGFpbmVyLmRhdGEoKSxcbiAgICAgICAgZW5kcG9pbnQgPSBlbmRwb2ludEZ1bmMocGFyYW1zKSxcbiAgICAgICAgbGltaXQgPSBwYXJzZUludChwYXJhbXMubGltaXQsIDEwKSB8fCA1MCxcbiAgICAgICAgdXJsUGFyYW1zID0gbmV3IFVSTFNlYXJjaFBhcmFtcyh3aW5kb3cubG9jYXRpb24uc2VhcmNoKSxcbiAgICAgICAgbmV3VXJsID0geHRCYXNlVXJsICsgZW5kcG9pbnQgKyAnLycgKyB4dG9vbHMuYXBwbGljYXRpb24udmFycy5vZmZzZXQsXG4gICAgICAgIG9sZFRvb2xQYXRoID0gbG9jYXRpb24ucGF0aG5hbWUuc3BsaXQoJy8nKVsxXSxcbiAgICAgICAgbmV3VG9vbFBhdGggPSBuZXdVcmwuc3BsaXQoJy8nKVsxXTtcblxuICAgIC8vIEdyYXkgb3V0IGNvbnRyaWJ1dGlvbnMgbGlzdC5cbiAgICAkY29udHJpYnV0aW9uc0NvbnRhaW5lci5hZGRDbGFzcygnY29udHJpYnV0aW9ucy1jb250YWluZXItLWxvYWRpbmcnKVxuXG4gICAgLy8gU2hvdyB0aGUgJ0xvYWRpbmcuLi4nIHRleHQuIENTUyB3aWxsIGhpZGUgdGhlIFwiUHJldmlvdXNcIiAvIFwiTmV4dFwiIGxpbmtzIHRvIHByZXZlbnQganVtcGluZy5cbiAgICAkY29udHJpYnV0aW9uc0xvYWRpbmcuc2hvdygpO1xuXG4gICAgdXJsUGFyYW1zLnNldCgnbGltaXQnLCBsaW1pdC50b1N0cmluZygpKTtcbiAgICB1cmxQYXJhbXMuYXBwZW5kKCdodG1sb25seScsICd5ZXMnKTtcblxuICAgIC8qKiBnbG9iYWw6IHh0QmFzZVVybCAqL1xuICAgICQuYWpheCh7XG4gICAgICAgIC8vIE1ha2Ugc3VyZSB0byBpbmNsdWRlIGFueSBVUkwgcGFyYW1ldGVycywgc3VjaCBhcyB0b29sPUh1Z2dsZSAoZm9yIEF1dG9FZGl0cykuXG4gICAgICAgIHVybDogbmV3VXJsICsgJz8nICsgdXJsUGFyYW1zLnRvU3RyaW5nKCksXG4gICAgICAgIHRpbWVvdXQ6IDYwMDAwXG4gICAgfSkuYWx3YXlzKGZ1bmN0aW9uICgpIHtcbiAgICAgICAgJGNvbnRyaWJ1dGlvbnNDb250YWluZXIucmVtb3ZlQ2xhc3MoJ2NvbnRyaWJ1dGlvbnMtY29udGFpbmVyLS1sb2FkaW5nJyk7XG4gICAgICAgICRjb250cmlidXRpb25zTG9hZGluZy5oaWRlKCk7XG4gICAgfSkuZG9uZShmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAkY29udHJpYnV0aW9uc0NvbnRhaW5lci5odG1sKGRhdGEpLnNob3coKTtcbiAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwQ29udHJpYnV0aW9uc05hdkxpc3RlbmVycyhlbmRwb2ludEZ1bmMsIGFwaVRpdGxlKTtcblxuICAgICAgICAvLyBTZXQgYW4gaW5pdGlhbCBvZmZzZXQgaWYgd2UgZG9uJ3QgaGF2ZSBvbmUgYWxyZWFkeSBzbyB0aGF0IHdlIGtub3cgd2hlbiB3ZSdyZSBvbiB0aGUgZmlyc3QgcGFnZSBvZiBjb250cmlicy5cbiAgICAgICAgaWYgKCF4dG9vbHMuYXBwbGljYXRpb24udmFycy5pbml0aWFsT2Zmc2V0KSB7XG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5pbml0aWFsT2Zmc2V0ID0gJCgnLmNvbnRyaWJzLXJvdy1kYXRlJykuZmlyc3QoKS5kYXRhKCd2YWx1ZScpO1xuXG4gICAgICAgICAgICAvLyBJbiB0aGlzIGNhc2Ugd2Uga25vdyB3ZSBhcmUgbG9hZGluZyBjb250cmlicyBmb3IgdGhpcyBmaXJzdCB0aW1lIHZpYSBBSkFYIChzdWNoIGFzIGF0IC9hdXRvZWRpdHMpLFxuICAgICAgICAgICAgLy8gaGVuY2Ugd2UnbGwgc2V0IHRoZSBpbml0aWFsTG9hZCBmbGFnIHRvIHRydWUsIHNvIHdlIGtub3cgbm90IHRvIHVubmVjZXNzYXJpbHkgcG9sbHV0ZSB0aGUgVVJMXG4gICAgICAgICAgICAvLyBhZnRlciB3ZSBnZXQgYmFjayB0aGUgZGF0YSAoc2VlIGJlbG93KS5cbiAgICAgICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLmluaXRpYWxMb2FkID0gdHJ1ZTtcbiAgICAgICAgfVxuXG4gICAgICAgIGlmIChvbGRUb29sUGF0aCAhPT0gbmV3VG9vbFBhdGgpIHtcbiAgICAgICAgICAgIC8vIEhhcHBlbnMgd2hlbiBhIHN1YnJlcXVlc3QgaXMgbWFkZSB0byBhIGRpZmZlcmVudCBjb250cm9sbGVyIGFjdGlvbi5cbiAgICAgICAgICAgIC8vIEZvciBpbnN0YW5jZSwgL2F1dG9lZGl0cyBlbWJlZHMgL25vbmF1dG9lZGl0cy1jb250cmlidXRpb25zLlxuICAgICAgICAgICAgdmFyIHJlZ2V4cCA9IG5ldyBSZWdFeHAoYF4vJHtuZXdUb29sUGF0aH0vKC4qKS9gKTtcbiAgICAgICAgICAgIG5ld1VybCA9IG5ld1VybC5yZXBsYWNlKHJlZ2V4cCwgYC8ke29sZFRvb2xQYXRofS8kMS9gKTtcbiAgICAgICAgfVxuXG4gICAgICAgIC8vIERvIG5vdCBydW4gb24gdGhlIGluaXRpYWwgcGFnZSBsb2FkLiBUaGlzIGlzIHRvIHJldGFpbiBhIGNsZWFuIFVSTDpcbiAgICAgICAgLy8gKGkuZS4gL2F1dG9lZGl0cy9lbndpa2kvRXhhbXBsZSwgcmF0aGVyIHRoYW4gL2F1dG9lZGl0cy9lbndpa2kvRXhhbXBsZS8wLy8vMjAxNS0wNy0wMlQxNTo1MDo0OD9saW1pdD01MClcbiAgICAgICAgLy8gV2hlbiB1c2VyIHBhZ2luYXRlcyAocmVxdWVzdHMgbWFkZSBOT1Qgb24gdGhlIGluaXRpYWwgcGFnZSBsb2FkKSwgd2UgZG8gd2FudCB0byB1cGRhdGUgdGhlIFVSTC5cbiAgICAgICAgaWYgKCF4dG9vbHMuYXBwbGljYXRpb24udmFycy5pbml0aWFsTG9hZCkge1xuICAgICAgICAgICAgLy8gVXBkYXRlIFVSTCBzbyB3ZSBjYW4gaGF2ZSBwZXJtYWxpbmtzLlxuICAgICAgICAgICAgLy8gJ2h0bWxvbmx5JyBzaG91bGQgYmUgcmVtb3ZlZCBhcyBpdCdzIGFuIGludGVybmFsIHBhcmFtLlxuICAgICAgICAgICAgdXJsUGFyYW1zLmRlbGV0ZSgnaHRtbG9ubHknKTtcbiAgICAgICAgICAgIHdpbmRvdy5oaXN0b3J5LnJlcGxhY2VTdGF0ZShcbiAgICAgICAgICAgICAgICBudWxsLFxuICAgICAgICAgICAgICAgIGRvY3VtZW50LnRpdGxlLFxuICAgICAgICAgICAgICAgIG5ld1VybCArICc/JyArIHVybFBhcmFtcy50b1N0cmluZygpXG4gICAgICAgICAgICApO1xuXG4gICAgICAgICAgICAvLyBBbHNvIHNjcm9sbCB0byB0aGUgdG9wIG9mIHRoZSBjb250cmlicyBjb250YWluZXIuXG4gICAgICAgICAgICAkY29udHJpYnV0aW9uc0NvbnRhaW5lci5wYXJlbnRzKCcucGFuZWwnKVswXS5zY3JvbGxJbnRvVmlldygpO1xuICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgLy8gU28gdGhhdCBwYWdpbmF0aW9uIHRocm91Z2ggdGhlIGNvbnRyaWJzIHdpbGwgdXBkYXRlIHRoZSBVUkwgYW5kIHNjcm9sbCBpbnRvIHZpZXcuXG4gICAgICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24udmFycy5pbml0aWFsTG9hZCA9IGZhbHNlO1xuICAgICAgICB9XG5cbiAgICAgICAgaWYgKHh0b29scy5hcHBsaWNhdGlvbi52YXJzLm9mZnNldCA8IHh0b29scy5hcHBsaWNhdGlvbi52YXJzLmluaXRpYWxPZmZzZXQpIHtcbiAgICAgICAgICAgICQoJy5jb250cmlidXRpb25zLS1wcmV2Jykuc2hvdygpO1xuICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgJCgnLmNvbnRyaWJ1dGlvbnMtLXByZXYnKS5oaWRlKCk7XG4gICAgICAgIH1cbiAgICAgICAgaWYgKCQoJy5jb250cmlidXRpb25zLXRhYmxlIHRib2R5IHRyJykubGVuZ3RoIDwgbGltaXQpIHtcbiAgICAgICAgICAgICQoJy5uZXh0LWVkaXRzJykuaGlkZSgpO1xuICAgICAgICB9XG4gICAgfSkuZmFpbChmdW5jdGlvbiAoX3hociwgX3N0YXR1cywgbWVzc2FnZSkge1xuICAgICAgICAkY29udHJpYnV0aW9uc0xvYWRpbmcuaGlkZSgpO1xuICAgICAgICAkY29udHJpYnV0aW9uc0NvbnRhaW5lci5odG1sKFxuICAgICAgICAgICAgJC5pMThuKCdhcGktZXJyb3InLCAkLmkxOG4oYXBpVGl0bGUpICsgJyBBUEk6IDxjb2RlPicgKyBtZXNzYWdlICsgJzwvY29kZT4nKVxuICAgICAgICApLnNob3coKTtcbiAgICB9KTtcbn07XG5cbi8qKlxuICogU2V0IHVwIGxpc3RlbmVycyBmb3IgbmF2aWdhdGluZyBjb250cmlidXRpb24gbGlzdHMuXG4gKi9cbnh0b29scy5hcHBsaWNhdGlvbi5zZXR1cENvbnRyaWJ1dGlvbnNOYXZMaXN0ZW5lcnMgPSBmdW5jdGlvbiAoZW5kcG9pbnRGdW5jLCBhcGlUaXRsZSkge1xuICAgIHNldEluaXRpYWxPZmZzZXQoKTtcblxuICAgIC8vIFByZXZpb3VzIGFycm93LlxuICAgICQoJy5jb250cmlidXRpb25zLS1wcmV2Jykub2ZmKCdjbGljaycpLm9uZSgnY2xpY2snLCBmdW5jdGlvbiAoZSkge1xuICAgICAgICBlLnByZXZlbnREZWZhdWx0KCk7XG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLm9mZnNldCA9IHh0b29scy5hcHBsaWNhdGlvbi52YXJzLnByZXZPZmZzZXRzLnBvcCgpXG4gICAgICAgICAgICB8fCB4dG9vbHMuYXBwbGljYXRpb24udmFycy5pbml0aWFsT2Zmc2V0O1xuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24ubG9hZENvbnRyaWJ1dGlvbnMoZW5kcG9pbnRGdW5jLCBhcGlUaXRsZSlcbiAgICB9KTtcblxuICAgIC8vIE5leHQgYXJyb3cuXG4gICAgJCgnLmNvbnRyaWJ1dGlvbnMtLW5leHQnKS5vZmYoJ2NsaWNrJykub25lKCdjbGljaycsIGZ1bmN0aW9uIChlKSB7XG4gICAgICAgIGUucHJldmVudERlZmF1bHQoKTtcbiAgICAgICAgaWYgKHh0b29scy5hcHBsaWNhdGlvbi52YXJzLm9mZnNldCkge1xuICAgICAgICAgICAgeHRvb2xzLmFwcGxpY2F0aW9uLnZhcnMucHJldk9mZnNldHMucHVzaCh4dG9vbHMuYXBwbGljYXRpb24udmFycy5vZmZzZXQpO1xuICAgICAgICB9XG4gICAgICAgIHh0b29scy5hcHBsaWNhdGlvbi52YXJzLm9mZnNldCA9ICQoJy5jb250cmlicy1yb3ctZGF0ZScpLmxhc3QoKS5kYXRhKCd2YWx1ZScpO1xuICAgICAgICB4dG9vbHMuYXBwbGljYXRpb24ubG9hZENvbnRyaWJ1dGlvbnMoZW5kcG9pbnRGdW5jLCBhcGlUaXRsZSk7XG4gICAgfSk7XG5cbiAgICAvLyBUaGUgJ0xpbWl0OicgZHJvcGRvd24uXG4gICAgJCgnI2NvbnRyaWJ1dGlvbnNfbGltaXQnKS5vbignY2hhbmdlJywgZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgdmFyIGxpbWl0ID0gcGFyc2VJbnQoZS50YXJnZXQudmFsdWUsIDEwKTtcbiAgICAgICAgJCgnLmNvbnRyaWJ1dGlvbnMtY29udGFpbmVyJykuZGF0YSgnbGltaXQnLCBsaW1pdCk7XG4gICAgICAgICQoJy5jb250cmlidXRpb25zLS1wcmV2LXRleHQnKS50ZXh0KFxuICAgICAgICAgICAgJC5pMThuKCdwYWdlci1uZXdlci1uJywgbGltaXQpLmNhcGl0YWxpemUoKVxuICAgICAgICApO1xuICAgICAgICAkKCcuY29udHJpYnV0aW9ucy0tbmV4dC10ZXh0JykudGV4dChcbiAgICAgICAgICAgICQuaTE4bigncGFnZXItb2xkZXItbicsIGxpbWl0KS5jYXBpdGFsaXplKClcbiAgICAgICAgKTtcbiAgICB9KTtcbn07XG4iLCIvKipcbiAqIENvcmUgSmF2YVNjcmlwdCBleHRlbnNpb25zXG4gKiBBZGFwdGVkIGZyb20gaHR0cHM6Ly9naXRodWIuY29tL011c2lrQW5pbWFsL3BhZ2V2aWV3c1xuICovXG5cblN0cmluZy5wcm90b3R5cGUuZGVzY29yZSA9IGZ1bmN0aW9uICgpIHtcbiAgICByZXR1cm4gdGhpcy5yZXBsYWNlKC9fL2csICcgJyk7XG59O1xuU3RyaW5nLnByb3RvdHlwZS5zY29yZSA9IGZ1bmN0aW9uICgpIHtcbiAgICByZXR1cm4gdGhpcy5yZXBsYWNlKC8gL2csICdfJyk7XG59O1xuU3RyaW5nLnByb3RvdHlwZS5lc2NhcGUgPSBmdW5jdGlvbiAoKSB7XG4gICAgdmFyIGVudGl0eU1hcCA9IHtcbiAgICAgICAgJyYnOiAnJmFtcDsnLFxuICAgICAgICAnPCc6ICcmbHQ7JyxcbiAgICAgICAgJz4nOiAnJmd0OycsXG4gICAgICAgICdcIic6ICcmcXVvdDsnLFxuICAgICAgICBcIidcIjogJyYjMzk7JyxcbiAgICAgICAgJy8nOiAnJiN4MkY7J1xuICAgIH07XG5cbiAgICByZXR1cm4gdGhpcy5yZXBsYWNlKC9bJjw+XCInXFwvXS9nLCBmdW5jdGlvbiAocykge1xuICAgICAgICByZXR1cm4gZW50aXR5TWFwW3NdO1xuICAgIH0pO1xufTtcblxuLy8gcmVtb3ZlIGR1cGxpY2F0ZSB2YWx1ZXMgZnJvbSBBcnJheVxuQXJyYXkucHJvdG90eXBlLnVuaXF1ZSA9IGZ1bmN0aW9uICgpIHtcbiAgICByZXR1cm4gdGhpcy5maWx0ZXIoZnVuY3Rpb24gKHZhbHVlLCBpbmRleCwgYXJyYXkpIHtcbiAgICAgICAgcmV0dXJuIGFycmF5LmluZGV4T2YodmFsdWUpID09PSBpbmRleDtcbiAgICB9KTtcbn07XG5cbi8qKiBodHRwczovL3N0YWNrb3ZlcmZsb3cuY29tL2EvMzI5MTg1Ni82MDQxNDIgKENDIEJZLVNBIDQuMCkgKi9cbk9iamVjdC5kZWZpbmVQcm9wZXJ0eShTdHJpbmcucHJvdG90eXBlLCAnY2FwaXRhbGl6ZScsIHtcbiAgICB2YWx1ZTogZnVuY3Rpb24gKCkge1xuICAgICAgICByZXR1cm4gdGhpcy5jaGFyQXQoMCkudG9VcHBlckNhc2UoKSArIHRoaXMuc2xpY2UoMSk7XG4gICAgfSxcbiAgICBlbnVtZXJhYmxlOiBmYWxzZVxufSk7XG4iLCJ4dG9vbHMuZWRpdGNvdW50ZXIgPSB7fTtcblxuLyoqXG4gKiBOYW1lc3BhY2VzIHRoYXQgaGF2ZSBiZWVuIGV4Y2x1ZGVkIGZyb20gdmlldyB2aWEgbmFtZXNwYWNlIHRvZ2dsZSB0YWJsZS5cbiAqIEB0eXBlIHtBcnJheX1cbiAqL1xueHRvb2xzLmVkaXRjb3VudGVyLmV4Y2x1ZGVkTmFtZXNwYWNlcyA9IFtdO1xuXG4vKipcbiAqIENoYXJ0IGxhYmVscyBmb3IgdGhlIG1vbnRoL3llYXJjb3VudCBjaGFydHMuXG4gKiBAdHlwZSB7T2JqZWN0fSBLZXlzIGFyZSB0aGUgY2hhcnQgSURzLCB2YWx1ZXMgYXJlIGFycmF5cyBvZiBzdHJpbmdzLlxuICovXG54dG9vbHMuZWRpdGNvdW50ZXIuY2hhcnRMYWJlbHMgPSB7fTtcblxuLyoqXG4gKiBOdW1iZXIgb2YgZGlnaXRzIG9mIHRoZSBtYXggbW9udGgveWVhciB0b3RhbC4gV2Ugd2FudCB0byBrZWVwIHRoaXMgY29uc2lzdGVudFxuICogZm9yIGFlc3RoZXRpYyByZWFzb25zLCBldmVuIGlmIHRoZSB1cGRhdGVkIHRvdGFscyBhcmUgZmV3ZXIgZGlnaXRzIGluIHNpemUuXG4gKiBAdHlwZSB7T2JqZWN0fSBLZXlzIGFyZSB0aGUgY2hhcnQgSURzLCB2YWx1ZXMgYXJlIGludGVnZXJzLlxuICovXG54dG9vbHMuZWRpdGNvdW50ZXIubWF4RGlnaXRzID0ge307XG5cbiQoZnVuY3Rpb24gKCkge1xuICAgIC8vIERvbid0IGRvIGFueXRoaW5nIGlmIHRoaXMgaXNuJ3QgYSBFZGl0IENvdW50ZXIgcGFnZS5cbiAgICBpZiAoJCgnYm9keS5lZGl0Y291bnRlcicpLmxlbmd0aCA9PT0gMCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwTXVsdGlTZWxlY3RMaXN0ZW5lcnMoKTtcblxuICAgIC8vIFNldCB1cCBjaGFydHMuXG4gICAgJCgnLmNoYXJ0LXdyYXBwZXInKS5lYWNoKGZ1bmN0aW9uICgpIHtcbiAgICAgICAgdmFyIGNoYXJ0VHlwZSA9ICQodGhpcykuZGF0YSgnY2hhcnQtdHlwZScpO1xuICAgICAgICBpZiAoY2hhcnRUeXBlID09PSB1bmRlZmluZWQpIHtcbiAgICAgICAgICAgIHJldHVybiBmYWxzZTtcbiAgICAgICAgfVxuICAgICAgICB2YXIgZGF0YSA9ICQodGhpcykuZGF0YSgnY2hhcnQtZGF0YScpO1xuICAgICAgICB2YXIgbGFiZWxzID0gJCh0aGlzKS5kYXRhKCdjaGFydC1sYWJlbHMnKTtcbiAgICAgICAgdmFyICRjdHggPSAkKCdjYW52YXMnLCAkKHRoaXMpKTtcblxuICAgICAgICAvKiogZ2xvYmFsOiBDaGFydCAqL1xuICAgICAgICBuZXcgQ2hhcnQoJGN0eCwge1xuICAgICAgICAgICAgdHlwZTogY2hhcnRUeXBlLFxuICAgICAgICAgICAgZGF0YToge1xuICAgICAgICAgICAgICAgIGxhYmVsczogbGFiZWxzLFxuICAgICAgICAgICAgICAgIGRhdGFzZXRzOiBbIHsgZGF0YTogZGF0YSB9IF1cbiAgICAgICAgICAgIH1cbiAgICAgICAgfSk7XG5cbiAgICAgICAgcmV0dXJuIHVuZGVmaW5lZDtcbiAgICB9KTtcblxuICAgIC8vIFNldCB1cCBuYW1lc3BhY2UgdG9nZ2xlIGNoYXJ0LlxuICAgIHh0b29scy5hcHBsaWNhdGlvbi5zZXR1cFRvZ2dsZVRhYmxlKHdpbmRvdy5uYW1lc3BhY2VUb3RhbHMsIHdpbmRvdy5uYW1lc3BhY2VDaGFydCwgbnVsbCwgdG9nZ2xlTmFtZXNwYWNlKTtcbn0pO1xuXG4vKipcbiAqIENhbGxiYWNrIGZvciBzZXR1cFRvZ2dsZVRhYmxlKCkuIFRoaXMgd2lsbCBzaG93L2hpZGUgYSBnaXZlbiBuYW1lc3BhY2UgZnJvbVxuICogYWxsIGNoYXJ0cywgYW5kIHVwZGF0ZSB0b3RhbHMgYW5kIHBlcmNlbnRhZ2VzLlxuICogQHBhcmFtIHtPYmplY3R9IG5ld0RhdGEgTmV3IG5hbWVzcGFjZXMgYW5kIHRvdGFscywgYXMgcmV0dXJuZWQgYnkgc2V0dXBUb2dnbGVUYWJsZS5cbiAqIEBwYXJhbSB7U3RyaW5nfSBrZXkgTmFtZXNwYWNlIElEIG9mIHRoZSB0b2dnbGVkIG5hbWVzcGFjZS5cbiAqL1xuZnVuY3Rpb24gdG9nZ2xlTmFtZXNwYWNlKG5ld0RhdGEsIGtleSlcbntcbiAgICB2YXIgdG90YWwgPSAwLCBjb3VudHMgPSBbXTtcbiAgICBPYmplY3Qua2V5cyhuZXdEYXRhKS5mb3JFYWNoKGZ1bmN0aW9uIChuYW1lc3BhY2UpIHtcbiAgICAgICAgdmFyIGNvdW50ID0gcGFyc2VJbnQobmV3RGF0YVtuYW1lc3BhY2VdLCAxMCk7XG4gICAgICAgIGNvdW50cy5wdXNoKGNvdW50KTtcbiAgICAgICAgdG90YWwgKz0gY291bnQ7XG4gICAgfSk7XG4gICAgdmFyIG5hbWVzcGFjZUNvdW50ID0gT2JqZWN0LmtleXMobmV3RGF0YSkubGVuZ3RoO1xuXG4gICAgLyoqIGdsb2JhbDogaTE4bkxhbmcgKi9cbiAgICAkKCcubmFtZXNwYWNlcy0tbmFtZXNwYWNlcycpLnRleHQoXG4gICAgICAgIG5hbWVzcGFjZUNvdW50LnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nKSArICcgJyArXG4gICAgICAgICQuaTE4bignbnVtLW5hbWVzcGFjZXMnLCBuYW1lc3BhY2VDb3VudClcbiAgICApO1xuICAgICQoJy5uYW1lc3BhY2VzLS1jb3VudCcpLnRleHQodG90YWwudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcpKTtcblxuICAgIC8vIE5vdyB0aGF0IHdlIGhhdmUgdGhlIHRvdGFsLCBsb29wIHRocm91Z2ggb25jZSBtb3JlIHRpbWUgdG8gdXBkYXRlIHBlcmNlbnRhZ2VzLlxuICAgIGNvdW50cy5mb3JFYWNoKGZ1bmN0aW9uIChjb3VudCkge1xuICAgICAgICAvLyBDYWxjdWxhdGUgcGVyY2VudGFnZSwgcm91bmRlZCB0byB0ZW50aHMuXG4gICAgICAgIHZhciBwZXJjZW50YWdlID0gZ2V0UGVyY2VudGFnZShjb3VudCwgdG90YWwpO1xuXG4gICAgICAgIC8vIFVwZGF0ZSB0ZXh0IHdpdGggbmV3IHZhbHVlIGFuZCBwZXJjZW50YWdlLlxuICAgICAgICAkKCcubmFtZXNwYWNlcy10YWJsZSAuc29ydC1lbnRyeS0tY291bnRbZGF0YS12YWx1ZT0nK2NvdW50KyddJykudGV4dChcbiAgICAgICAgICAgIGNvdW50LnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nKSArICcgKCcgKyBwZXJjZW50YWdlICsgJyknXG4gICAgICAgICk7XG4gICAgfSk7XG5cbiAgICAvLyBMb29wIHRocm91Z2ggbW9udGggYW5kIHllYXIgY2hhcnRzLCB0b2dnbGluZyB0aGUgZGF0YXNldCBmb3IgdGhlIG5ld2x5IGV4Y2x1ZGVkIG5hbWVzcGFjZS5cbiAgICBbJ3llYXInLCAnbW9udGgnXS5mb3JFYWNoKGZ1bmN0aW9uIChpZCkge1xuICAgICAgICB2YXIgY2hhcnRPYmogPSB3aW5kb3dbaWQgKyAnY291bnRzQ2hhcnQnXSxcbiAgICAgICAgICAgIG5zTmFtZSA9IHdpbmRvdy5uYW1lc3BhY2VzW2tleV0gfHwgJC5pMThuKCdtYWluc3BhY2UnKTtcblxuICAgICAgICAvLyBZZWFyIGFuZCBtb250aCBzZWN0aW9ucyBjYW4gYmUgc2VsZWN0aXZlbHkgaGlkZGVuLlxuICAgICAgICBpZiAoIWNoYXJ0T2JqKSB7XG4gICAgICAgICAgICByZXR1cm47XG4gICAgICAgIH1cblxuICAgICAgICAvLyBGaWd1cmUgb3V0IHRoZSBpbmRleCBvZiB0aGUgbmFtZXNwYWNlIHdlJ3JlIHRvZ2dsaW5nIHdpdGhpbiB0aGlzIGNoYXJ0IG9iamVjdC5cbiAgICAgICAgdmFyIGRhdGFzZXRJbmRleCA9IDA7XG4gICAgICAgIGNoYXJ0T2JqLmRhdGEuZGF0YXNldHMuZm9yRWFjaChmdW5jdGlvbiAoZGF0YXNldCwgaSkge1xuICAgICAgICAgICAgaWYgKGRhdGFzZXQubGFiZWwgPT09IG5zTmFtZSkge1xuICAgICAgICAgICAgICAgIGRhdGFzZXRJbmRleCA9IGk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0pO1xuXG4gICAgICAgIC8vIEZldGNoIHRoZSBtZXRhZGF0YSBhbmQgdG9nZ2xlIHRoZSBoaWRkZW4gcHJvcGVydHkuXG4gICAgICAgIHZhciBtZXRhID0gY2hhcnRPYmouZ2V0RGF0YXNldE1ldGEoZGF0YXNldEluZGV4KTtcbiAgICAgICAgbWV0YS5oaWRkZW4gPSBtZXRhLmhpZGRlbiA9PT0gbnVsbCA/ICFjaGFydE9iai5kYXRhLmRhdGFzZXRzW2RhdGFzZXRJbmRleF0uaGlkZGVuIDogbnVsbDtcblxuICAgICAgICAvLyBBZGQgdGhpcyBuYW1lc3BhY2UgdG8gdGhlIGxpc3Qgb2YgZXhjbHVkZWQgbmFtZXNwYWNlcy5cbiAgICAgICAgaWYgKG1ldGEuaGlkZGVuKSB7XG4gICAgICAgICAgICB4dG9vbHMuZWRpdGNvdW50ZXIuZXhjbHVkZWROYW1lc3BhY2VzLnB1c2gobnNOYW1lKTtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIHh0b29scy5lZGl0Y291bnRlci5leGNsdWRlZE5hbWVzcGFjZXMgPSB4dG9vbHMuZWRpdGNvdW50ZXIuZXhjbHVkZWROYW1lc3BhY2VzLmZpbHRlcihmdW5jdGlvbiAobmFtZXNwYWNlKSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIG5hbWVzcGFjZSAhPT0gbnNOYW1lO1xuICAgICAgICAgICAgfSk7XG4gICAgICAgIH1cblxuICAgICAgICAvLyBVcGRhdGUgeS1heGlzIGxhYmVscyB3aXRoIHRoZSBuZXcgdG90YWxzLlxuICAgICAgICB3aW5kb3dbaWQgKyAnY291bnRzQ2hhcnQnXS5jb25maWcuZGF0YS5sYWJlbHMgPSBnZXRZQXhpc0xhYmVscyhpZCwgY2hhcnRPYmouZGF0YS5kYXRhc2V0cyk7XG5cbiAgICAgICAgLy8gUmVmcmVzaCBjaGFydC5cbiAgICAgICAgY2hhcnRPYmoudXBkYXRlKCk7XG4gICAgfSk7XG59XG5cbi8qKlxuICogQnVpbGQgdGhlIGxhYmVscyBmb3IgdGhlIHktYXhpcyBvZiB0aGUgeWVhci9tb250aGNvdW50IGNoYXJ0cywgd2hpY2ggaW5jbHVkZSB0aGUgeWVhci9tb250aCBhbmQgdGhlIHRvdGFsIG51bWJlciBvZlxuICogZWRpdHMgYWNyb3NzIGFsbCBuYW1lc3BhY2VzIGluIHRoYXQgeWVhci9tb250aC5cbiAqIEBwYXJhbSB7U3RyaW5nfSBpZCBJRCBwcmVmaXggb2YgdGhlIGNoYXJ0LCBlaXRoZXIgJ21vbnRoJyBvciAneWVhcicuXG4gKiBAcGFyYW0ge0FycmF5fSBkYXRhc2V0cyBEYXRhc2V0cyBtYWtpbmcgdXAgdGhlIGNoYXJ0LlxuICogQHJldHVybiB7QXJyYXl9IExhYmVscyBmb3IgZWFjaCB5ZWFyL21vbnRoLlxuICovXG5mdW5jdGlvbiBnZXRZQXhpc0xhYmVscyhpZCwgZGF0YXNldHMpXG57XG4gICAgdmFyIGxhYmVsc0FuZFRvdGFscyA9IGdldE1vbnRoWWVhclRvdGFscyhpZCwgZGF0YXNldHMpO1xuXG4gICAgLy8gRm9ybWF0IGxhYmVscyB3aXRoIHRvdGFscyBuZXh0IHRvIHRoZW0uIFRoaXMgaXMgYSBiaXQgaGFja3ksIGJ1dCBpdCB3b3JrcyEgV2UgdXNlIHRhYnMgKFxcdCkgdG8gbWFrZSB0aGVcbiAgICAvLyBsYWJlbHMvdG90YWxzIGZvciBlYWNoIG5hbWVzcGFjZSBsaW5lIHVwIHBlcmZlY3RseS4gVGhlIGNhdmVhdCBpcyB0aGF0IHdlIGNhbid0IGxvY2FsaXplIHRoZSBudW1iZXJzIGJlY2F1c2VcbiAgICAvLyB0aGUgY29tbWFzIGFyZSBub3QgbW9ub3NwYWNlZCA6KFxuICAgIHJldHVybiBPYmplY3Qua2V5cyhsYWJlbHNBbmRUb3RhbHMpLm1hcChmdW5jdGlvbiAoeWVhcikge1xuICAgICAgICB2YXIgZGlnaXRDb3VudCA9IGxhYmVsc0FuZFRvdGFsc1t5ZWFyXS50b1N0cmluZygpLmxlbmd0aDtcbiAgICAgICAgdmFyIG51bVRhYnMgPSAoeHRvb2xzLmVkaXRjb3VudGVyLm1heERpZ2l0c1tpZF0gLSBkaWdpdENvdW50KSAqIDI7XG5cbiAgICAgICAgLy8gKzUgZm9yIGEgYml0IG9mIGV4dHJhIHNwYWNpbmcuXG4gICAgICAgIC8qKiBnbG9iYWw6IGkxOG5MYW5nICovXG4gICAgICAgIHJldHVybiB5ZWFyICsgQXJyYXkobnVtVGFicyArIDUpLmpvaW4oXCJcXHRcIikgK1xuICAgICAgICAgICAgbGFiZWxzQW5kVG90YWxzW3llYXJdLnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nLCB7dXNlR3JvdXBpbmc6IGZhbHNlfSk7XG4gICAgfSk7XG59XG5cbi8qKlxuICogR2V0IHRoZSB0b3RhbCBudW1iZXIgb2YgZWRpdHMgZm9yIHRoZSBnaXZlbiBkYXRhc2V0ICh5ZWFyIG9yIG1vbnRoKS5cbiAqIEBwYXJhbSB7U3RyaW5nfSBpZCBJRCBwcmVmaXggb2YgdGhlIGNoYXJ0LCBlaXRoZXIgJ21vbnRoJyBvciAneWVhcicuXG4gKiBAcGFyYW0ge0FycmF5fSBkYXRhc2V0cyBEYXRhc2V0cyBtYWtpbmcgdXAgdGhlIGNoYXJ0LlxuICogQHJldHVybiB7T2JqZWN0fSBMYWJlbHMgZm9yIGVhY2ggeWVhci9tb250aCBhcyBrZXlzLCB0b3RhbHMgYXMgdGhlIHZhbHVlcy5cbiAqL1xuZnVuY3Rpb24gZ2V0TW9udGhZZWFyVG90YWxzKGlkLCBkYXRhc2V0cylcbntcbiAgICB2YXIgbGFiZWxzQW5kVG90YWxzID0ge307XG4gICAgZGF0YXNldHMuZm9yRWFjaChmdW5jdGlvbiAobmFtZXNwYWNlKSB7XG4gICAgICAgIGlmICh4dG9vbHMuZWRpdGNvdW50ZXIuZXhjbHVkZWROYW1lc3BhY2VzLmluZGV4T2YobmFtZXNwYWNlLmxhYmVsKSAhPT0gLTEpIHtcbiAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgfVxuXG4gICAgICAgIG5hbWVzcGFjZS5kYXRhLmZvckVhY2goZnVuY3Rpb24gKGNvdW50LCBpbmRleCkge1xuICAgICAgICAgICAgaWYgKCFsYWJlbHNBbmRUb3RhbHNbeHRvb2xzLmVkaXRjb3VudGVyLmNoYXJ0TGFiZWxzW2lkXVtpbmRleF1dKSB7XG4gICAgICAgICAgICAgICAgbGFiZWxzQW5kVG90YWxzW3h0b29scy5lZGl0Y291bnRlci5jaGFydExhYmVsc1tpZF1baW5kZXhdXSA9IDA7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICBsYWJlbHNBbmRUb3RhbHNbeHRvb2xzLmVkaXRjb3VudGVyLmNoYXJ0TGFiZWxzW2lkXVtpbmRleF1dICs9IGNvdW50O1xuICAgICAgICB9KTtcbiAgICB9KTtcblxuICAgIHJldHVybiBsYWJlbHNBbmRUb3RhbHM7XG59XG5cbi8qKlxuICogQ2FsY3VsYXRlIGFuZCBmb3JtYXQgYSBwZXJjZW50YWdlLCByb3VuZGVkIHRvIHRoZSB0ZW50aHMgcGxhY2UuXG4gKiBAcGFyYW0ge051bWJlcn0gbnVtZXJhdG9yXG4gKiBAcGFyYW0ge051bWJlcn0gZGVub21pbmF0b3JcbiAqIEByZXR1cm4ge051bWJlcn1cbiAqL1xuZnVuY3Rpb24gZ2V0UGVyY2VudGFnZShudW1lcmF0b3IsIGRlbm9taW5hdG9yKVxue1xuICAgIC8qKiBnbG9iYWw6IGkxOG5MYW5nICovXG4gICAgcmV0dXJuIChudW1lcmF0b3IgLyBkZW5vbWluYXRvcikudG9Mb2NhbGVTdHJpbmcoaTE4bkxhbmcsIHtzdHlsZTogJ3BlcmNlbnQnfSk7XG59XG5cbi8qKlxuICogU2V0IHVwIHRoZSBtb250aGNvdW50cyBvciB5ZWFyY291bnRzIGNoYXJ0LiBUaGlzIGlzIHNldCBvbiB0aGUgd2luZG93XG4gKiBiZWNhdXNlIGl0IGlzIGNhbGxlZCBpbiB0aGUgeWVhcmNvdW50cy9tb250aGNvdW50cyB2aWV3LlxuICogQHBhcmFtIHtTdHJpbmd9IGlkICd5ZWFyJyBvciAnbW9udGgnLlxuICogQHBhcmFtIHtBcnJheX0gZGF0YXNldHMgRGF0YXNldHMgZ3JvdXBlZCBieSBtYWluc3BhY2UuXG4gKiBAcGFyYW0ge0FycmF5fSBsYWJlbHMgVGhlIGJhcmUgbGFiZWxzIGZvciB0aGUgeS1heGlzICh5ZWFycyBvciBtb250aHMpLlxuICogQHBhcmFtIHtOdW1iZXJ9IG1heFRvdGFsIE1heGltdW0gdmFsdWUgb2YgeWVhci9tb250aCB0b3RhbHMuXG4gKiBAcGFyYW0ge0Jvb2xlYW59IHNob3dMZWdlbmQgV2hldGhlciB0byBzaG93IHRoZSBsZWdlbmQgYWJvdmUgdGhlIGNoYXJ0LlxuICovXG54dG9vbHMuZWRpdGNvdW50ZXIuc2V0dXBNb250aFllYXJDaGFydCA9IGZ1bmN0aW9uIChpZCwgZGF0YXNldHMsIGxhYmVscywgbWF4VG90YWwsIHNob3dMZWdlbmQpIHtcbiAgICAvKiogQHR5cGUge0FycmF5fSBMYWJlbHMgZm9yIGVhY2ggbmFtZXNwYWNlLiAqL1xuICAgIHZhciBuYW1lc3BhY2VzID0gZGF0YXNldHMubWFwKGZ1bmN0aW9uIChkYXRhc2V0KSB7XG4gICAgICAgIHJldHVybiBkYXRhc2V0LmxhYmVsO1xuICAgIH0pO1xuXG4gICAgeHRvb2xzLmVkaXRjb3VudGVyLm1heERpZ2l0c1tpZF0gPSBtYXhUb3RhbC50b1N0cmluZygpLmxlbmd0aDtcbiAgICB4dG9vbHMuZWRpdGNvdW50ZXIuY2hhcnRMYWJlbHNbaWRdID0gbGFiZWxzO1xuXG4gICAgLyoqIGdsb2JhbDogaTE4blJUTCAqL1xuICAgIC8qKiBnbG9iYWw6IGkxOG5MYW5nICovXG4gICAgd2luZG93W2lkICsgJ2NvdW50c0NoYXJ0J10gPSBuZXcgQ2hhcnQoJCgnIycgKyBpZCArICdjb3VudHMtY2FudmFzJyksIHtcbiAgICAgICAgdHlwZTogJ2hvcml6b250YWxCYXInLFxuICAgICAgICBkYXRhOiB7XG4gICAgICAgICAgICBsYWJlbHM6IGdldFlBeGlzTGFiZWxzKGlkLCBkYXRhc2V0cyksXG4gICAgICAgICAgICBkYXRhc2V0czogZGF0YXNldHNcbiAgICAgICAgfSxcbiAgICAgICAgb3B0aW9uczoge1xuICAgICAgICAgICAgdG9vbHRpcHM6IHtcbiAgICAgICAgICAgICAgICBtb2RlOiAnbmVhcmVzdCcsXG4gICAgICAgICAgICAgICAgaW50ZXJzZWN0OiB0cnVlLFxuICAgICAgICAgICAgICAgIGNhbGxiYWNrczoge1xuICAgICAgICAgICAgICAgICAgICBsYWJlbDogZnVuY3Rpb24gKHRvb2x0aXApIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhciBsYWJlbHNBbmRUb3RhbHMgPSBnZXRNb250aFllYXJUb3RhbHMoaWQsIGRhdGFzZXRzKSxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICB0b3RhbHMgPSBPYmplY3Qua2V5cyhsYWJlbHNBbmRUb3RhbHMpLm1hcChmdW5jdGlvbiAobGFiZWwpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGxhYmVsc0FuZFRvdGFsc1tsYWJlbF07XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfSksXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgdG90YWwgPSB0b3RhbHNbdG9vbHRpcC5pbmRleF0sXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgcGVyY2VudGFnZSA9IGdldFBlcmNlbnRhZ2UodG9vbHRpcC54TGFiZWwsIHRvdGFsKTtcblxuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHRvb2x0aXAueExhYmVsLnRvTG9jYWxlU3RyaW5nKGkxOG5MYW5nKSArICcgJyArXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgJygnICsgcGVyY2VudGFnZSArICcpJztcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgdGl0bGU6IGZ1bmN0aW9uICh0b29sdGlwKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICB2YXIgeUxhYmVsID0gdG9vbHRpcFswXS55TGFiZWwucmVwbGFjZSgvXFx0LiovLCAnJyk7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4geUxhYmVsICsgJyAtICcgKyBuYW1lc3BhY2VzW3Rvb2x0aXBbMF0uZGF0YXNldEluZGV4XTtcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICByZXNwb25zaXZlOiB0cnVlLFxuICAgICAgICAgICAgbWFpbnRhaW5Bc3BlY3RSYXRpbzogZmFsc2UsXG4gICAgICAgICAgICBzY2FsZXM6IHtcbiAgICAgICAgICAgICAgICB4QXhlczogW3tcbiAgICAgICAgICAgICAgICAgICAgc3RhY2tlZDogdHJ1ZSxcbiAgICAgICAgICAgICAgICAgICAgdGlja3M6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGJlZ2luQXRaZXJvOiB0cnVlLFxuICAgICAgICAgICAgICAgICAgICAgICAgcmV2ZXJzZTogaTE4blJUTCxcbiAgICAgICAgICAgICAgICAgICAgICAgIGNhbGxiYWNrOiBmdW5jdGlvbiAodmFsdWUpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBpZiAoTWF0aC5mbG9vcih2YWx1ZSkgPT09IHZhbHVlKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiB2YWx1ZS50b0xvY2FsZVN0cmluZyhpMThuTGFuZyk7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBncmlkTGluZXM6IHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbG9yOiB4dG9vbHMuYXBwbGljYXRpb24uY2hhcnRHcmlkQ29sb3JcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIH1dLFxuICAgICAgICAgICAgICAgIHlBeGVzOiBbe1xuICAgICAgICAgICAgICAgICAgICBzdGFja2VkOiB0cnVlLFxuICAgICAgICAgICAgICAgICAgICBiYXJUaGlja25lc3M6IDE4LFxuICAgICAgICAgICAgICAgICAgICBwb3NpdGlvbjogaTE4blJUTCA/ICdyaWdodCcgOiAnbGVmdCcsXG4gICAgICAgICAgICAgICAgICAgIGdyaWRMaW5lczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29sb3I6IHh0b29scy5hcHBsaWNhdGlvbi5jaGFydEdyaWRDb2xvclxuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgfV1cbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICBsZWdlbmQ6IHtcbiAgICAgICAgICAgICAgICBkaXNwbGF5OiBzaG93TGVnZW5kXG4gICAgICAgICAgICB9XG4gICAgICAgIH1cbiAgICB9KTtcbn07XG5cbi8qKlxuICogQnVpbGRzIHRoZSB0aW1lY2FyZCBjaGFydCBhbmQgYWRkcyBhIGxpc3RlbmVyIGZvciB0aGUgJ2xvY2FsIHRpbWUnIG9wdGlvbi5cbiAqIEBwYXJhbSB7QXJyYXl9IHRpbWVDYXJkRGF0YXNldHNcbiAqIEBwYXJhbSB7T2JqZWN0fSBkYXlzXG4gKi9cbnh0b29scy5lZGl0Y291bnRlci5zZXR1cFRpbWVjYXJkID0gZnVuY3Rpb24gKHRpbWVDYXJkRGF0YXNldHMsIGRheXMpIHtcbiAgICB2YXIgdXNlTG9jYWxUaW1lem9uZSA9IGZhbHNlLFxuICAgICAgICB0aW1lem9uZU9mZnNldCA9IG5ldyBEYXRlKCkuZ2V0VGltZXpvbmVPZmZzZXQoKSAvIDYwO1xuICAgIHdpbmRvdy5jaGFydCA9IG5ldyBDaGFydCgkKFwiI3RpbWVjYXJkLWJ1YmJsZS1jaGFydFwiKSwge1xuICAgICAgICB0eXBlOiAnYnViYmxlJyxcbiAgICAgICAgZGF0YToge1xuICAgICAgICAgICAgZGF0YXNldHM6IHRpbWVDYXJkRGF0YXNldHNcbiAgICAgICAgfSxcbiAgICAgICAgb3B0aW9uczoge1xuICAgICAgICAgICAgcmVzcG9uc2l2ZTogdHJ1ZSxcbiAgICAgICAgICAgIC8vIG1haW50YWluQXNwZWN0UmF0aW86IGZhbHNlLFxuICAgICAgICAgICAgbGVnZW5kOiB7XG4gICAgICAgICAgICAgICAgZGlzcGxheTogZmFsc2VcbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICBsYXlvdXQ6IHtcbiAgICAgICAgICAgICAgICBwYWRkaW5nOiB7XG4gICAgICAgICAgICAgICAgICAgIHJpZ2h0OiAwXG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIGVsZW1lbnRzOiB7XG4gICAgICAgICAgICAgICAgcG9pbnQ6IHtcbiAgICAgICAgICAgICAgICAgICAgcmFkaXVzOiBmdW5jdGlvbiAoY29udGV4dCkge1xuICAgICAgICAgICAgICAgICAgICAgICAgdmFyIGluZGV4ID0gY29udGV4dC5kYXRhSW5kZXg7XG4gICAgICAgICAgICAgICAgICAgICAgICB2YXIgZGF0YSA9IGNvbnRleHQuZGF0YXNldC5kYXRhW2luZGV4XTtcbiAgICAgICAgICAgICAgICAgICAgICAgIC8vIHZhciBzaXplID0gY29udGV4dC5jaGFydC53aWR0aDtcbiAgICAgICAgICAgICAgICAgICAgICAgIC8vIHZhciBiYXNlID0gZGF0YS52YWx1ZSAvIDEwMDtcbiAgICAgICAgICAgICAgICAgICAgICAgIC8vIHJldHVybiAoc2l6ZSAvIDUwKSAqIGJhc2U7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gZGF0YS5zY2FsZTtcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgaGl0UmFkaXVzOiA4XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIHNjYWxlczoge1xuICAgICAgICAgICAgICAgIHlBeGVzOiBbe1xuICAgICAgICAgICAgICAgICAgICB0aWNrczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgbWluOiAwLFxuICAgICAgICAgICAgICAgICAgICAgICAgbWF4OiA4LFxuICAgICAgICAgICAgICAgICAgICAgICAgc3RlcFNpemU6IDEsXG4gICAgICAgICAgICAgICAgICAgICAgICBwYWRkaW5nOiAyNSxcbiAgICAgICAgICAgICAgICAgICAgICAgIGNhbGxiYWNrOiBmdW5jdGlvbiAodmFsdWUsIGluZGV4KSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGRheXNbaW5kZXhdO1xuICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBwb3NpdGlvbjogaTE4blJUTCA/ICdyaWdodCcgOiAnbGVmdCcsXG4gICAgICAgICAgICAgICAgICAgIGdyaWRMaW5lczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgY29sb3I6IHh0b29scy5hcHBsaWNhdGlvbi5jaGFydEdyaWRDb2xvclxuICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgfSwge1xuICAgICAgICAgICAgICAgICAgICB0aWNrczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgbWluOiAwLFxuICAgICAgICAgICAgICAgICAgICAgICAgbWF4OiA4LFxuICAgICAgICAgICAgICAgICAgICAgICAgc3RlcFNpemU6IDEsXG4gICAgICAgICAgICAgICAgICAgICAgICBwYWRkaW5nOiAyNSxcbiAgICAgICAgICAgICAgICAgICAgICAgIGNhbGxiYWNrOiBmdW5jdGlvbiAodmFsdWUsIGluZGV4KSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgaWYgKGluZGV4ID09PSAwIHx8IGluZGV4ID4gNykge1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gJyc7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiB0aW1lQ2FyZERhdGFzZXRzW2luZGV4IC0gMV0uZGF0YS5yZWR1Y2UoZnVuY3Rpb24gKGEsIGIpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGEgKyBwYXJzZUludChiLnZhbHVlLCAxMCk7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfSwgMCk7XG4gICAgICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgICAgIHBvc2l0aW9uOiBpMThuUlRMID8gJ2xlZnQnIDogJ3JpZ2h0J1xuICAgICAgICAgICAgICAgIH1dLFxuICAgICAgICAgICAgICAgIHhBeGVzOiBbe1xuICAgICAgICAgICAgICAgICAgICB0aWNrczoge1xuICAgICAgICAgICAgICAgICAgICAgICAgYmVnaW5BdFplcm86IHRydWUsXG4gICAgICAgICAgICAgICAgICAgICAgICBtaW46IDAsXG4gICAgICAgICAgICAgICAgICAgICAgICBtYXg6IDIzLFxuICAgICAgICAgICAgICAgICAgICAgICAgc3RlcFNpemU6IDEsXG4gICAgICAgICAgICAgICAgICAgICAgICByZXZlcnNlOiBpMThuUlRMLFxuICAgICAgICAgICAgICAgICAgICAgICAgcGFkZGluZzogMCxcbiAgICAgICAgICAgICAgICAgICAgICAgIGNhbGxiYWNrOiBmdW5jdGlvbiAodmFsdWUpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBpZiAodmFsdWUgJSAyID09PSAwKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiB2YWx1ZSArIFwiOjAwXCI7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuICcnO1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICAgICAgZ3JpZExpbmVzOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb2xvcjogeHRvb2xzLmFwcGxpY2F0aW9uLmNoYXJ0R3JpZENvbG9yXG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9XVxuICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIHRvb2x0aXBzOiB7XG4gICAgICAgICAgICAgICAgZGlzcGxheUNvbG9yczogZmFsc2UsXG4gICAgICAgICAgICAgICAgY2FsbGJhY2tzOiB7XG4gICAgICAgICAgICAgICAgICAgIHRpdGxlOiBmdW5jdGlvbiAoaXRlbXMpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiBkYXlzWzcgLSBpdGVtc1swXS55TGFiZWwgKyAxXSArICcgJyArIGl0ZW1zWzBdLnhMYWJlbCArICc6MDAnO1xuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBsYWJlbDogZnVuY3Rpb24gKGl0ZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHZhciBudW1FZGl0cyA9IFt0aW1lQ2FyZERhdGFzZXRzW2l0ZW0uZGF0YXNldEluZGV4XS5kYXRhW2l0ZW0uaW5kZXhdLnZhbHVlXTtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybmAke251bUVkaXRzfSAkeyQuaTE4bignbnVtLWVkaXRzJywgW251bUVkaXRzXSl9YDtcbiAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH1cbiAgICAgICAgfVxuICAgIH0pO1xuXG4gICAgJChmdW5jdGlvbiAoKSB7XG4gICAgICAgICQoJy51c2UtbG9jYWwtdGltZScpXG4gICAgICAgICAgICAucHJvcCgnY2hlY2tlZCcsIGZhbHNlKVxuICAgICAgICAgICAgLm9uKCdjbGljaycsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgICAgICAgICB2YXIgb2Zmc2V0ID0gJCh0aGlzKS5pcygnOmNoZWNrZWQnKSA/IHRpbWV6b25lT2Zmc2V0IDogLXRpbWV6b25lT2Zmc2V0O1xuICAgICAgICAgICAgICAgIGNoYXJ0LmRhdGEuZGF0YXNldHMgPSBjaGFydC5kYXRhLmRhdGFzZXRzLm1hcChmdW5jdGlvbiAoZGF5KSB7XG4gICAgICAgICAgICAgICAgICAgIGRheS5kYXRhID0gZGF5LmRhdGEubWFwKGZ1bmN0aW9uIChkYXR1bSkge1xuICAgICAgICAgICAgICAgICAgICAgICAgdmFyIG5ld0hvdXIgPSAocGFyc2VJbnQoZGF0dW0uaG91ciwgMTApIC0gb2Zmc2V0KSAlIDI0O1xuICAgICAgICAgICAgICAgICAgICAgICAgaWYgKG5ld0hvdXIgPCAwKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgbmV3SG91ciA9IDI0ICsgbmV3SG91cjtcbiAgICAgICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAgICAgICAgIGRhdHVtLmhvdXIgPSBuZXdIb3VyLnRvU3RyaW5nKCk7XG4gICAgICAgICAgICAgICAgICAgICAgICBkYXR1bS54ID0gbmV3SG91ci50b1N0cmluZygpO1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGRhdHVtO1xuICAgICAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIGRheTtcbiAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgICAgICB1c2VMb2NhbFRpbWV6b25lID0gdHJ1ZTtcbiAgICAgICAgICAgICAgICBjaGFydC51cGRhdGUoKTtcbiAgICAgICAgICAgIH0pO1xuICAgIH0pO1xufVxuIiwieHRvb2xzLmdsb2JhbGNvbnRyaWJzID0ge307XG5cbiQoZnVuY3Rpb24gKCkge1xuICAgIC8vIERvbid0IGRvIGFueXRoaW5nIGlmIHRoaXMgaXNuJ3QgYSBHbG9iYWwgQ29udHJpYnMgcGFnZS5cbiAgICBpZiAoJCgnYm9keS5nbG9iYWxjb250cmlicycpLmxlbmd0aCA9PT0gMCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgeHRvb2xzLmFwcGxpY2F0aW9uLnNldHVwQ29udHJpYnV0aW9uc05hdkxpc3RlbmVycyhmdW5jdGlvbiAocGFyYW1zKSB7XG4gICAgICAgIHJldHVybiBgZ2xvYmFsY29udHJpYnMvJHtwYXJhbXMudXNlcm5hbWV9LyR7cGFyYW1zLm5hbWVzcGFjZX0vJHtwYXJhbXMuc3RhcnR9LyR7cGFyYW1zLmVuZH1gO1xuICAgIH0sICdnbG9iYWxjb250cmlicycpO1xufSk7XG4iLCJ4dG9vbHMucGFnZXMgPSB7fTtcblxuJChmdW5jdGlvbiAoKSB7XG4gICAgLy8gRG9uJ3QgZXhlY3V0ZSB0aGlzIGNvZGUgaWYgd2UncmUgbm90IG9uIHRoZSBQYWdlcyB0b29sXG4gICAgLy8gRklYTUU6IGZpbmQgYSB3YXkgdG8gYXV0b21hdGUgdGhpcyBzb21laG93Li4uXG4gICAgaWYgKCEkKCdib2R5LnBhZ2VzJykubGVuZ3RoKSB7XG4gICAgICAgIHJldHVybjtcbiAgICB9XG5cbiAgICB2YXIgZGVsZXRpb25TdW1tYXJpZXMgPSB7fTtcblxuICAgIHh0b29scy5hcHBsaWNhdGlvbi5zZXR1cFRvZ2dsZVRhYmxlKHdpbmRvdy5jb3VudHNCeU5hbWVzcGFjZSwgd2luZG93LnBpZUNoYXJ0LCAnY291bnQnLCBmdW5jdGlvbiAobmV3RGF0YSkge1xuICAgICAgICB2YXIgdG90YWxzID0ge1xuICAgICAgICAgICAgY291bnQ6IDAsXG4gICAgICAgICAgICBkZWxldGVkOiAwLFxuICAgICAgICAgICAgcmVkaXJlY3RzOiAwLFxuICAgICAgICB9O1xuICAgICAgICBPYmplY3Qua2V5cyhuZXdEYXRhKS5mb3JFYWNoKGZ1bmN0aW9uIChucykge1xuICAgICAgICAgICAgdG90YWxzLmNvdW50ICs9IG5ld0RhdGFbbnNdLmNvdW50O1xuICAgICAgICAgICAgdG90YWxzLmRlbGV0ZWQgKz0gbmV3RGF0YVtuc10uZGVsZXRlZDtcbiAgICAgICAgICAgIHRvdGFscy5yZWRpcmVjdHMgKz0gbmV3RGF0YVtuc10ucmVkaXJlY3RzO1xuICAgICAgICB9KTtcbiAgICAgICAgJCgnLm5hbWVzcGFjZXMtLW5hbWVzcGFjZXMnKS50ZXh0KFxuICAgICAgICAgICAgT2JqZWN0LmtleXMobmV3RGF0YSkubGVuZ3RoLnRvTG9jYWxlU3RyaW5nKCkgKyBcIiBcIiArXG4gICAgICAgICAgICAkLmkxOG4oXG4gICAgICAgICAgICAgICAgJ251bS1uYW1lc3BhY2VzJyxcbiAgICAgICAgICAgICAgICBPYmplY3Qua2V5cyhuZXdEYXRhKS5sZW5ndGgsXG4gICAgICAgICAgICApXG4gICAgICAgICk7XG4gICAgICAgICQoJy5uYW1lc3BhY2VzLS1wYWdlcycpLnRleHQodG90YWxzLmNvdW50LnRvTG9jYWxlU3RyaW5nKCkpO1xuICAgICAgICAkKCcubmFtZXNwYWNlcy0tZGVsZXRlZCcpLnRleHQoXG4gICAgICAgICAgICB0b3RhbHMuZGVsZXRlZC50b0xvY2FsZVN0cmluZygpICsgXCIgKFwiICtcbiAgICAgICAgICAgICgodG90YWxzLmRlbGV0ZWQgLyB0b3RhbHMuY291bnQpICogMTAwKS50b0ZpeGVkKDEpICsgXCIlKVwiXG4gICAgICAgICk7XG4gICAgICAgICQoJy5uYW1lc3BhY2VzLS1yZWRpcmVjdHMnKS50ZXh0KFxuICAgICAgICAgICAgdG90YWxzLnJlZGlyZWN0cy50b0xvY2FsZVN0cmluZygpICsgXCIgKFwiICtcbiAgICAgICAgICAgICgodG90YWxzLnJlZGlyZWN0cyAvIHRvdGFscy5jb3VudCkgKiAxMDApLnRvRml4ZWQoMSkgKyBcIiUpXCJcbiAgICAgICAgKTtcbiAgICB9KTtcblxuICAgICQoJy5kZWxldGVkLXBhZ2UnKS5vbignbW91c2VvdmVyJywgZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgdmFyIHBhZ2UgPSAkKHRoaXMpLmRhdGEoJ3BhZ2UnKSxcbiAgICAgICAgICAgIHN0YXJ0VGltZSA9ICQodGhpcykuZGF0YSgnZGF0ZXRpbWUnKS50b1N0cmluZygpLnNsaWNlKDAsIC0yKTtcblxuICAgICAgICB2YXIgc2hvd1N1bW1hcnkgPSBmdW5jdGlvbiAoc3VtbWFyeSkge1xuICAgICAgICAgICAgJChlLnRhcmdldCkuZmluZCgnLnRvb2x0aXAtYm9keScpLmh0bWwoc3VtbWFyeSk7XG4gICAgICAgIH07XG5cbiAgICAgICAgaWYgKGRlbGV0aW9uU3VtbWFyaWVzW3BhZ2VdICE9PSB1bmRlZmluZWQpIHtcbiAgICAgICAgICAgIHJldHVybiBzaG93U3VtbWFyeShkZWxldGlvblN1bW1hcmllc1twYWdlXSk7XG4gICAgICAgIH1cblxuICAgICAgICB2YXIgbG9nRXZlbnRzUXVlcnkgPSBmdW5jdGlvbiAoYWN0aW9uKSB7XG4gICAgICAgICAgICByZXR1cm4gJC5hamF4KHtcbiAgICAgICAgICAgICAgICB1cmw6IHdpa2lBcGksXG4gICAgICAgICAgICAgICAgZGF0YToge1xuICAgICAgICAgICAgICAgICAgICBhY3Rpb246ICdxdWVyeScsXG4gICAgICAgICAgICAgICAgICAgIGxpc3Q6ICdsb2dldmVudHMnLFxuICAgICAgICAgICAgICAgICAgICBsZXRpdGxlOiBwYWdlLFxuICAgICAgICAgICAgICAgICAgICBsZXN0YXJ0OiBzdGFydFRpbWUsXG4gICAgICAgICAgICAgICAgICAgIGxldHlwZTogJ2RlbGV0ZScsXG4gICAgICAgICAgICAgICAgICAgIGxlYWN0aW9uOiBhY3Rpb24gfHwgJ2RlbGV0ZS9kZWxldGUnLFxuICAgICAgICAgICAgICAgICAgICBsZWxpbWl0OiAxLFxuICAgICAgICAgICAgICAgICAgICBmb3JtYXQ6ICdqc29uJ1xuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgZGF0YVR5cGU6ICdqc29ucCdcbiAgICAgICAgICAgIH0pXG4gICAgICAgIH07XG5cbiAgICAgICAgdmFyIHNob3dQYXJzZXJBcGlGYWlsdXJlID0gZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgcmV0dXJuIHNob3dTdW1tYXJ5KFwiPHNwYW4gY2xhc3M9J3RleHQtZGFuZ2VyJz5cIiArICQuaTE4bignYXBpLWVycm9yJywgJ1BhcnNlciBBUEknKSArIFwiPC9zcGFuPlwiKTtcbiAgICAgICAgfTtcblxuICAgICAgICB2YXIgc2hvd0xvZ2dpbmdBcGlGYWlsdXJlID0gZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgcmV0dXJuIHNob3dTdW1tYXJ5KFwiPHNwYW4gY2xhc3M9J3RleHQtZGFuZ2VyJz5cIiArICQuaTE4bignYXBpLWVycm9yJywgJ0xvZ2dpbmcgQVBJJykgKyBcIjwvc3Bhbj5cIik7XG4gICAgICAgIH07XG5cbiAgICAgICAgdmFyIHNob3dQYXJzZWRXaWtpdGV4dCA9IGZ1bmN0aW9uIChldmVudCkge1xuICAgICAgICAgICAgcmV0dXJuICQuYWpheCh7XG4gICAgICAgICAgICAgICAgdXJsOiB4dEJhc2VVcmwgKyAnYXBpL3Byb2plY3QvcGFyc2VyLycgKyB3aWtpRG9tYWluICsgJz93aWtpdGV4dD0nICsgZW5jb2RlVVJJQ29tcG9uZW50KGV2ZW50LmNvbW1lbnQpXG4gICAgICAgICAgICB9KS5kb25lKGZ1bmN0aW9uIChtYXJrdXApIHtcbiAgICAgICAgICAgICAgICAvLyBHZXQgdGltZXN0YW1wIGluIFlZWVktTU0tREQgSEg6TU0gZm9ybWF0LlxuICAgICAgICAgICAgICAgIHZhciB0aW1lc3RhbXAgPSBuZXcgRGF0ZShldmVudC50aW1lc3RhbXApXG4gICAgICAgICAgICAgICAgICAgIC50b0lTT1N0cmluZygpXG4gICAgICAgICAgICAgICAgICAgIC5zbGljZSgwLCAxNilcbiAgICAgICAgICAgICAgICAgICAgLnJlcGxhY2UoJ1QnLCAnICcpO1xuXG4gICAgICAgICAgICAgICAgLy8gQWRkIHRpbWVzdGFtcCBhbmQgbGluayB0byBhZG1pbi5cbiAgICAgICAgICAgICAgICB2YXIgc3VtbWFyeSA9IHRpbWVzdGFtcCArIFwiICg8YSB0YXJnZXQ9J19ibGFuaycgaHJlZj0naHR0cHM6Ly9cIiArIHdpa2lEb21haW4gK1xuICAgICAgICAgICAgICAgICAgICBcIi93aWtpL1VzZXI6XCIgKyBldmVudC51c2VyICsgXCInPlwiICsgZXZlbnQudXNlciArICc8L2E+KTogPGk+JyArIG1hcmt1cCArICc8L2k+JztcblxuICAgICAgICAgICAgICAgIGRlbGV0aW9uU3VtbWFyaWVzW3BhZ2VdID0gc3VtbWFyeTtcbiAgICAgICAgICAgICAgICBzaG93U3VtbWFyeShzdW1tYXJ5KTtcbiAgICAgICAgICAgIH0pLmZhaWwoc2hvd1BhcnNlckFwaUZhaWx1cmUpO1xuICAgICAgICB9O1xuXG4gICAgICAgIGxvZ0V2ZW50c1F1ZXJ5KCkuZG9uZShmdW5jdGlvbiAocmVzcCkge1xuICAgICAgICAgICAgdmFyIGV2ZW50ID0gcmVzcC5xdWVyeS5sb2dldmVudHNbMF07XG5cbiAgICAgICAgICAgIGlmICghZXZlbnQpIHtcbiAgICAgICAgICAgICAgICAvLyBUcnkgYWdhaW4gYnV0IGxvb2sgZm9yIHJlZGlyZWN0IGRlbGV0aW9ucy5cbiAgICAgICAgICAgICAgICByZXR1cm4gbG9nRXZlbnRzUXVlcnkoJ2RlbGV0ZS9kZWxldGVfcmVkaXInKS5kb25lKGZ1bmN0aW9uIChyZXNwKSB7XG4gICAgICAgICAgICAgICAgICAgIGV2ZW50ID0gcmVzcC5xdWVyeS5sb2dldmVudHNbMF07XG5cbiAgICAgICAgICAgICAgICAgICAgaWYgKCFldmVudCkge1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHNob3dQYXJzZXJBcGlGYWlsdXJlKCk7XG4gICAgICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgICAgICBzaG93UGFyc2VkV2lraXRleHQoZXZlbnQpO1xuICAgICAgICAgICAgICAgIH0pLmZhaWwoc2hvd0xvZ2dpbmdBcGlGYWlsdXJlKTtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgc2hvd1BhcnNlZFdpa2l0ZXh0KGV2ZW50KTtcbiAgICAgICAgfSkuZmFpbChzaG93TG9nZ2luZ0FwaUZhaWx1cmUpO1xuICAgIH0pO1xufSk7XG4iLCJ4dG9vbHMudG9wZWRpdHMgPSB7fTtcblxuJChmdW5jdGlvbiAoKSB7XG4gICAgLy8gRG9uJ3QgZXhlY3V0ZSB0aGlzIGNvZGUgaWYgd2UncmUgbm90IG9uIHRoZSBUb3BFZGl0cyB0b29sLlxuICAgIC8vIEZJWE1FOiBmaW5kIGEgd2F5IHRvIGF1dG9tYXRlIHRoaXMgc29tZWhvdy4uLlxuICAgIGlmICghJCgnYm9keS50b3BlZGl0cycpLmxlbmd0aCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgLy8gRGlzYWJsZSB0aGUgYXJ0aWNsZSBpbnB1dCBpZiB0aGV5IHNlbGVjdCB0aGUgJ0FsbCcgbmFtZXNwYWNlIG9wdGlvblxuICAgICQoJyNuYW1lc3BhY2Vfc2VsZWN0Jykub24oJ2NoYW5nZScsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgJCgnI2FydGljbGVfaW5wdXQnKS5wcm9wKCdkaXNhYmxlZCcsICQodGhpcykudmFsKCkgPT09ICdhbGwnKTtcbiAgICB9KTtcbn0pO1xuIiwiLyohXG4gKiBib290c3RyYXAtdHlwZWFoZWFkLmpzIHYwLjAuNSAoaHR0cDovL3d3dy51cGJvb3RzdHJhcC5jb20pXG4gKiBDb3B5cmlnaHQgMjAxMi0yMDE1IFR3aXR0ZXIgSW5jLlxuICogTGljZW5zZWQgdW5kZXIgTUlUIChodHRwczovL2dpdGh1Yi5jb20vYmlnZ29yYS9ib290c3RyYXAtYWpheC10eXBlYWhlYWQvYmxvYi9tYXN0ZXIvTElDRU5TRSlcbiAqIFNlZSBEZW1vOiBodHRwOi8vcGx1Z2lucy51cGJvb3RzdHJhcC5jb20vYm9vdHN0cmFwLWFqYXgtdHlwZWFoZWFkXG4gKiBVcGRhdGVkOiAyMDE1LTA0LTA1IDExOjQzOjU2XG4gKlxuICogTW9kaWZpY2F0aW9ucyBieSBQYXVsIFdhcmVsaXMgYW5kIEFsZXhleSBHb3JkZXlldlxuICovXG4hZnVuY3Rpb24gKCQpIHtcblxuICAgIFwidXNlIHN0cmljdFwiOyAvLyBqc2hpbnQgO187XG5cbiAgICAvKiBUWVBFQUhFQUQgUFVCTElDIENMQVNTIERFRklOSVRJT05cbiAgICAgKiA9PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0gKi9cblxuICAgIHZhciBUeXBlYWhlYWQgPSBmdW5jdGlvbiAoZWxlbWVudCwgb3B0aW9ucykge1xuXG4gICAgICAgIC8vZGVhbCB3aXRoIHNjcm9sbEJhclxuICAgICAgICB2YXIgZGVmYXVsdE9wdGlvbnMgPSAkLmZuLnR5cGVhaGVhZC5kZWZhdWx0cztcbiAgICAgICAgaWYgKG9wdGlvbnMuc2Nyb2xsQmFyKSB7XG4gICAgICAgICAgICBvcHRpb25zLml0ZW1zID0gMTAwO1xuICAgICAgICAgICAgb3B0aW9ucy5tZW51ID0gJzx1bCBjbGFzcz1cInR5cGVhaGVhZCBkcm9wZG93bi1tZW51XCIgc3R5bGU9XCJtYXgtaGVpZ2h0OjIyMHB4O292ZXJmbG93OmF1dG87XCI+PC91bD4nO1xuICAgICAgICB9XG5cbiAgICAgICAgdmFyIHRoYXQgPSB0aGlzO1xuICAgICAgICB0aGF0LiRlbGVtZW50ID0gJChlbGVtZW50KTtcbiAgICAgICAgdGhhdC5vcHRpb25zID0gJC5leHRlbmQoe30sICQuZm4udHlwZWFoZWFkLmRlZmF1bHRzLCBvcHRpb25zKTtcbiAgICAgICAgdGhhdC4kbWVudSA9ICQodGhhdC5vcHRpb25zLm1lbnUpLmluc2VydEFmdGVyKHRoYXQuJGVsZW1lbnQpO1xuXG4gICAgICAgIC8vIE1ldGhvZCBvdmVycmlkZXNcbiAgICAgICAgdGhhdC5ldmVudFN1cHBvcnRlZCA9IHRoYXQub3B0aW9ucy5ldmVudFN1cHBvcnRlZCB8fCB0aGF0LmV2ZW50U3VwcG9ydGVkO1xuICAgICAgICB0aGF0LmdyZXBwZXIgPSB0aGF0Lm9wdGlvbnMuZ3JlcHBlciB8fCB0aGF0LmdyZXBwZXI7XG4gICAgICAgIHRoYXQuaGlnaGxpZ2h0ZXIgPSB0aGF0Lm9wdGlvbnMuaGlnaGxpZ2h0ZXIgfHwgdGhhdC5oaWdobGlnaHRlcjtcbiAgICAgICAgdGhhdC5sb29rdXAgPSB0aGF0Lm9wdGlvbnMubG9va3VwIHx8IHRoYXQubG9va3VwO1xuICAgICAgICB0aGF0Lm1hdGNoZXIgPSB0aGF0Lm9wdGlvbnMubWF0Y2hlciB8fCB0aGF0Lm1hdGNoZXI7XG4gICAgICAgIHRoYXQucmVuZGVyID0gdGhhdC5vcHRpb25zLnJlbmRlciB8fCB0aGF0LnJlbmRlcjtcbiAgICAgICAgdGhhdC5vblNlbGVjdCA9IHRoYXQub3B0aW9ucy5vblNlbGVjdCB8fCBudWxsO1xuICAgICAgICB0aGF0LnNvcnRlciA9IHRoYXQub3B0aW9ucy5zb3J0ZXIgfHwgdGhhdC5zb3J0ZXI7XG4gICAgICAgIHRoYXQuc291cmNlID0gdGhhdC5vcHRpb25zLnNvdXJjZSB8fCB0aGF0LnNvdXJjZTtcbiAgICAgICAgdGhhdC5kaXNwbGF5RmllbGQgPSB0aGF0Lm9wdGlvbnMuZGlzcGxheUZpZWxkIHx8IHRoYXQuZGlzcGxheUZpZWxkO1xuICAgICAgICB0aGF0LnZhbHVlRmllbGQgPSB0aGF0Lm9wdGlvbnMudmFsdWVGaWVsZCB8fCB0aGF0LnZhbHVlRmllbGQ7XG5cbiAgICAgICAgaWYgKHRoYXQub3B0aW9ucy5hamF4KSB7XG4gICAgICAgICAgICB2YXIgYWpheCA9IHRoYXQub3B0aW9ucy5hamF4O1xuXG4gICAgICAgICAgICBpZiAodHlwZW9mIGFqYXggPT09ICdzdHJpbmcnKSB7XG4gICAgICAgICAgICAgICAgdGhhdC5hamF4ID0gJC5leHRlbmQoe30sICQuZm4udHlwZWFoZWFkLmRlZmF1bHRzLmFqYXgsIHtcbiAgICAgICAgICAgICAgICAgICAgdXJsOiBhamF4XG4gICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgICAgIGlmICh0eXBlb2YgYWpheC5kaXNwbGF5RmllbGQgPT09ICdzdHJpbmcnKSB7XG4gICAgICAgICAgICAgICAgICAgIHRoYXQuZGlzcGxheUZpZWxkID0gdGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZCA9IGFqYXguZGlzcGxheUZpZWxkO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICBpZiAodHlwZW9mIGFqYXgudmFsdWVGaWVsZCA9PT0gJ3N0cmluZycpIHtcbiAgICAgICAgICAgICAgICAgICAgdGhhdC52YWx1ZUZpZWxkID0gdGhhdC5vcHRpb25zLnZhbHVlRmllbGQgPSBhamF4LnZhbHVlRmllbGQ7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgdGhhdC5hamF4ID0gJC5leHRlbmQoe30sICQuZm4udHlwZWFoZWFkLmRlZmF1bHRzLmFqYXgsIGFqYXgpO1xuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICBpZiAoIXRoYXQuYWpheC51cmwpIHtcbiAgICAgICAgICAgICAgICB0aGF0LmFqYXggPSBudWxsO1xuICAgICAgICAgICAgfVxuICAgICAgICAgICAgdGhhdC5xdWVyeSA9IFwiXCI7XG4gICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICB0aGF0LnNvdXJjZSA9IHRoYXQub3B0aW9ucy5zb3VyY2U7XG4gICAgICAgICAgICB0aGF0LmFqYXggPSBudWxsO1xuICAgICAgICB9XG4gICAgICAgIHRoYXQuc2hvd24gPSBmYWxzZTtcbiAgICAgICAgdGhhdC5saXN0ZW4oKTtcbiAgICB9O1xuXG4gICAgVHlwZWFoZWFkLnByb3RvdHlwZSA9IHtcbiAgICAgICAgY29uc3RydWN0b3I6IFR5cGVhaGVhZCxcbiAgICAgICAgLy89PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09XG4gICAgICAgIC8vICBVdGlsc1xuICAgICAgICAvLyAgQ2hlY2sgaWYgYW4gZXZlbnQgaXMgc3VwcG9ydGVkIGJ5IHRoZSBicm93c2VyIGVnLiAna2V5cHJlc3MnXG4gICAgICAgIC8vICAqIFRoaXMgd2FzIGluY2x1ZGVkIHRvIGhhbmRsZSB0aGUgXCJleGhhdXN0aXZlIGRlcHJlY2F0aW9uXCIgb2YgalF1ZXJ5LmJyb3dzZXIgaW4galF1ZXJ5IDEuOFxuICAgICAgICAvLz09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT1cbiAgICAgICAgZXZlbnRTdXBwb3J0ZWQ6IGZ1bmN0aW9uIChldmVudE5hbWUpIHtcbiAgICAgICAgICAgIHZhciBpc1N1cHBvcnRlZCA9IChldmVudE5hbWUgaW4gdGhpcy4kZWxlbWVudCk7XG5cbiAgICAgICAgICAgIGlmICghaXNTdXBwb3J0ZWQpIHtcbiAgICAgICAgICAgICAgICB0aGlzLiRlbGVtZW50LnNldEF0dHJpYnV0ZShldmVudE5hbWUsICdyZXR1cm47Jyk7XG4gICAgICAgICAgICAgICAgaXNTdXBwb3J0ZWQgPSB0eXBlb2YgdGhpcy4kZWxlbWVudFtldmVudE5hbWVdID09PSAnZnVuY3Rpb24nO1xuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICByZXR1cm4gaXNTdXBwb3J0ZWQ7XG4gICAgICAgIH0sXG4gICAgICAgIHNlbGVjdDogZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgdmFyICRzZWxlY3RlZEl0ZW0gPSB0aGlzLiRtZW51LmZpbmQoJy5hY3RpdmUnKTtcbiAgICAgICAgICAgIHZhciB2YWx1ZSA9ICRzZWxlY3RlZEl0ZW0uYXR0cignZGF0YS12YWx1ZScpO1xuICAgICAgICAgICAgdmFyIHRleHQgPSB0aGlzLiRtZW51LmZpbmQoJy5hY3RpdmUgYScpLnRleHQoKTtcblxuICAgICAgICAgICAgaWYgKHRoaXMub3B0aW9ucy5vblNlbGVjdCkge1xuICAgICAgICAgICAgICAgIHRoaXMub3B0aW9ucy5vblNlbGVjdCh7XG4gICAgICAgICAgICAgICAgICAgIHZhbHVlOiB2YWx1ZSxcbiAgICAgICAgICAgICAgICAgICAgdGV4dDogdGV4dFxuICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgfVxuICAgICAgICAgICAgdGhpcy4kZWxlbWVudFxuICAgICAgICAgICAgICAgIC52YWwodGhpcy51cGRhdGVyKHRleHQpKVxuICAgICAgICAgICAgICAgIC5jaGFuZ2UoKTtcbiAgICAgICAgICAgIHJldHVybiB0aGlzLmhpZGUoKTtcbiAgICAgICAgfSxcbiAgICAgICAgdXBkYXRlcjogZnVuY3Rpb24gKGl0ZW0pIHtcbiAgICAgICAgICAgIHJldHVybiBpdGVtO1xuICAgICAgICB9LFxuICAgICAgICBzaG93OiBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICB2YXIgcG9zID0gJC5leHRlbmQoe30sIHRoaXMuJGVsZW1lbnQucG9zaXRpb24oKSwge1xuICAgICAgICAgICAgICAgIGhlaWdodDogdGhpcy4kZWxlbWVudFswXS5vZmZzZXRIZWlnaHRcbiAgICAgICAgICAgIH0pO1xuXG4gICAgICAgICAgICB0aGlzLiRtZW51LmNzcyh7XG4gICAgICAgICAgICAgICAgdG9wOiBwb3MudG9wICsgcG9zLmhlaWdodCxcbiAgICAgICAgICAgICAgICBsZWZ0OiBwb3MubGVmdFxuICAgICAgICAgICAgfSk7XG5cbiAgICAgICAgICAgIGlmKHRoaXMub3B0aW9ucy5hbGlnbldpZHRoKSB7XG4gICAgICAgICAgICAgICAgdmFyIHdpZHRoID0gJCh0aGlzLiRlbGVtZW50WzBdKS5vdXRlcldpZHRoKCk7XG4gICAgICAgICAgICAgICAgdGhpcy4kbWVudS5jc3Moe1xuICAgICAgICAgICAgICAgICAgICB3aWR0aDogd2lkdGhcbiAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgdGhpcy4kbWVudS5zaG93KCk7XG4gICAgICAgICAgICB0aGlzLnNob3duID0gdHJ1ZTtcbiAgICAgICAgICAgIHJldHVybiB0aGlzO1xuICAgICAgICB9LFxuICAgICAgICBoaWRlOiBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICB0aGlzLiRtZW51LmhpZGUoKTtcbiAgICAgICAgICAgIHRoaXMuc2hvd24gPSBmYWxzZTtcbiAgICAgICAgICAgIHJldHVybiB0aGlzO1xuICAgICAgICB9LFxuICAgICAgICBhamF4TG9va3VwOiBmdW5jdGlvbiAoKSB7XG5cbiAgICAgICAgICAgIHZhciBxdWVyeSA9ICQudHJpbSh0aGlzLiRlbGVtZW50LnZhbCgpKTtcblxuICAgICAgICAgICAgaWYgKHF1ZXJ5ID09PSB0aGlzLnF1ZXJ5KSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIHRoaXM7XG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIC8vIFF1ZXJ5IGNoYW5nZWRcbiAgICAgICAgICAgIHRoaXMucXVlcnkgPSBxdWVyeTtcblxuICAgICAgICAgICAgLy8gQ2FuY2VsIGxhc3QgdGltZXIgaWYgc2V0XG4gICAgICAgICAgICBpZiAodGhpcy5hamF4LnRpbWVySWQpIHtcbiAgICAgICAgICAgICAgICBjbGVhclRpbWVvdXQodGhpcy5hamF4LnRpbWVySWQpO1xuICAgICAgICAgICAgICAgIHRoaXMuYWpheC50aW1lcklkID0gbnVsbDtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgaWYgKCFxdWVyeSB8fCBxdWVyeS5sZW5ndGggPCB0aGlzLmFqYXgudHJpZ2dlckxlbmd0aCkge1xuICAgICAgICAgICAgICAgIC8vIGNhbmNlbCB0aGUgYWpheCBjYWxsYmFjayBpZiBpbiBwcm9ncmVzc1xuICAgICAgICAgICAgICAgIGlmICh0aGlzLmFqYXgueGhyKSB7XG4gICAgICAgICAgICAgICAgICAgIHRoaXMuYWpheC54aHIuYWJvcnQoKTtcbiAgICAgICAgICAgICAgICAgICAgdGhpcy5hamF4LnhociA9IG51bGw7XG4gICAgICAgICAgICAgICAgICAgIHRoaXMuYWpheFRvZ2dsZUxvYWRDbGFzcyhmYWxzZSk7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgcmV0dXJuIHRoaXMuc2hvd24gPyB0aGlzLmhpZGUoKSA6IHRoaXM7XG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIGZ1bmN0aW9uIGV4ZWN1dGUoKSB7XG4gICAgICAgICAgICAgICAgdGhpcy5hamF4VG9nZ2xlTG9hZENsYXNzKHRydWUpO1xuXG4gICAgICAgICAgICAgICAgLy8gQ2FuY2VsIGxhc3QgY2FsbCBpZiBhbHJlYWR5IGluIHByb2dyZXNzXG4gICAgICAgICAgICAgICAgaWYgKHRoaXMuYWpheC54aHIpXG4gICAgICAgICAgICAgICAgICAgIHRoaXMuYWpheC54aHIuYWJvcnQoKTtcblxuICAgICAgICAgICAgICAgIHZhciBwYXJhbXMgPSB0aGlzLmFqYXgucHJlRGlzcGF0Y2ggPyB0aGlzLmFqYXgucHJlRGlzcGF0Y2gocXVlcnkpIDoge1xuICAgICAgICAgICAgICAgICAgICBxdWVyeTogcXVlcnlcbiAgICAgICAgICAgICAgICB9O1xuICAgICAgICAgICAgICAgIHRoaXMuYWpheC54aHIgPSAkLmFqYXgoe1xuICAgICAgICAgICAgICAgICAgICB1cmw6IHRoaXMuYWpheC51cmwsXG4gICAgICAgICAgICAgICAgICAgIGRhdGE6IHBhcmFtcyxcbiAgICAgICAgICAgICAgICAgICAgc3VjY2VzczogJC5wcm94eSh0aGlzLmFqYXhTb3VyY2UsIHRoaXMpLFxuICAgICAgICAgICAgICAgICAgICB0eXBlOiB0aGlzLmFqYXgubWV0aG9kIHx8ICdnZXQnLFxuICAgICAgICAgICAgICAgICAgICBkYXRhVHlwZTogJ2pzb25wJ1xuICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIHRoaXMuYWpheC50aW1lcklkID0gbnVsbDtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgLy8gUXVlcnkgaXMgZ29vZCB0byBzZW5kLCBzZXQgYSB0aW1lclxuICAgICAgICAgICAgdGhpcy5hamF4LnRpbWVySWQgPSBzZXRUaW1lb3V0KCQucHJveHkoZXhlY3V0ZSwgdGhpcyksIHRoaXMuYWpheC50aW1lb3V0KTtcblxuICAgICAgICAgICAgcmV0dXJuIHRoaXM7XG4gICAgICAgIH0sXG4gICAgICAgIGFqYXhTb3VyY2U6IGZ1bmN0aW9uIChkYXRhKSB7XG4gICAgICAgICAgICB0aGlzLmFqYXhUb2dnbGVMb2FkQ2xhc3MoZmFsc2UpO1xuICAgICAgICAgICAgdmFyIHRoYXQgPSB0aGlzLCBpdGVtcztcbiAgICAgICAgICAgIGlmICghdGhhdC5hamF4LnhocilcbiAgICAgICAgICAgICAgICByZXR1cm47XG4gICAgICAgICAgICBpZiAodGhhdC5hamF4LnByZVByb2Nlc3MpIHtcbiAgICAgICAgICAgICAgICBkYXRhID0gdGhhdC5hamF4LnByZVByb2Nlc3MoZGF0YSk7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICAvLyBTYXZlIGZvciBzZWxlY3Rpb24gcmV0cmVpdmFsXG4gICAgICAgICAgICB0aGF0LmFqYXguZGF0YSA9IGRhdGE7XG5cbiAgICAgICAgICAgIC8vIE1hbmlwdWxhdGUgb2JqZWN0c1xuICAgICAgICAgICAgaXRlbXMgPSB0aGF0LmdyZXBwZXIodGhhdC5hamF4LmRhdGEpIHx8IFtdO1xuICAgICAgICAgICAgaWYgKCFpdGVtcy5sZW5ndGgpIHtcbiAgICAgICAgICAgICAgICByZXR1cm4gdGhhdC5zaG93biA/IHRoYXQuaGlkZSgpIDogdGhhdDtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgdGhhdC5hamF4LnhociA9IG51bGw7XG4gICAgICAgICAgICByZXR1cm4gdGhhdC5yZW5kZXIoaXRlbXMuc2xpY2UoMCwgdGhhdC5vcHRpb25zLml0ZW1zKSkuc2hvdygpO1xuICAgICAgICB9LFxuICAgICAgICBhamF4VG9nZ2xlTG9hZENsYXNzOiBmdW5jdGlvbiAoZW5hYmxlKSB7XG4gICAgICAgICAgICBpZiAoIXRoaXMuYWpheC5sb2FkaW5nQ2xhc3MpXG4gICAgICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICAgICAgdGhpcy4kZWxlbWVudC50b2dnbGVDbGFzcyh0aGlzLmFqYXgubG9hZGluZ0NsYXNzLCBlbmFibGUpO1xuICAgICAgICB9LFxuICAgICAgICBsb29rdXA6IGZ1bmN0aW9uIChldmVudCkge1xuICAgICAgICAgICAgdmFyIHRoYXQgPSB0aGlzLCBpdGVtcztcbiAgICAgICAgICAgIGlmICh0aGF0LmFqYXgpIHtcbiAgICAgICAgICAgICAgICB0aGF0LmFqYXhlcigpO1xuICAgICAgICAgICAgfVxuICAgICAgICAgICAgZWxzZSB7XG4gICAgICAgICAgICAgICAgdGhhdC5xdWVyeSA9IHRoYXQuJGVsZW1lbnQudmFsKCk7XG5cbiAgICAgICAgICAgICAgICBpZiAoIXRoYXQucXVlcnkpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHRoYXQuc2hvd24gPyB0aGF0LmhpZGUoKSA6IHRoYXQ7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAgICAgaXRlbXMgPSB0aGF0LmdyZXBwZXIodGhhdC5zb3VyY2UpO1xuXG5cbiAgICAgICAgICAgICAgICBpZiAoIWl0ZW1zKSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiB0aGF0LnNob3duID8gdGhhdC5oaWRlKCkgOiB0aGF0O1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICAvL0JoYW51IGFkZGVkIGEgY3VzdG9tIG1lc3NhZ2UtIFJlc3VsdCBub3QgRm91bmQgd2hlbiBubyByZXN1bHQgaXMgZm91bmRcbiAgICAgICAgICAgICAgICBpZiAoaXRlbXMubGVuZ3RoID09IDApIHtcbiAgICAgICAgICAgICAgICAgICAgaXRlbXNbMF0gPSB7J2lkJzogLTIxLCAnbmFtZSc6IFwiUmVzdWx0IG5vdCBGb3VuZFwifVxuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICByZXR1cm4gdGhhdC5yZW5kZXIoaXRlbXMuc2xpY2UoMCwgdGhhdC5vcHRpb25zLml0ZW1zKSkuc2hvdygpO1xuICAgICAgICAgICAgfVxuICAgICAgICB9LFxuICAgICAgICBtYXRjaGVyOiBmdW5jdGlvbiAoaXRlbSkge1xuICAgICAgICAgICAgcmV0dXJuIH5pdGVtLnRvTG93ZXJDYXNlKCkuaW5kZXhPZih0aGlzLnF1ZXJ5LnRvTG93ZXJDYXNlKCkpO1xuICAgICAgICB9LFxuICAgICAgICBzb3J0ZXI6IGZ1bmN0aW9uIChpdGVtcykge1xuICAgICAgICAgICAgaWYgKCF0aGlzLm9wdGlvbnMuYWpheCkge1xuICAgICAgICAgICAgICAgIHZhciBiZWdpbnN3aXRoID0gW10sXG4gICAgICAgICAgICAgICAgICAgIGNhc2VTZW5zaXRpdmUgPSBbXSxcbiAgICAgICAgICAgICAgICAgICAgY2FzZUluc2Vuc2l0aXZlID0gW10sXG4gICAgICAgICAgICAgICAgICAgIGl0ZW07XG5cbiAgICAgICAgICAgICAgICB3aGlsZSAoaXRlbSA9IGl0ZW1zLnNoaWZ0KCkpIHtcbiAgICAgICAgICAgICAgICAgICAgaWYgKCFpdGVtLnRvTG93ZXJDYXNlKCkuaW5kZXhPZih0aGlzLnF1ZXJ5LnRvTG93ZXJDYXNlKCkpKVxuICAgICAgICAgICAgICAgICAgICAgICAgYmVnaW5zd2l0aC5wdXNoKGl0ZW0pO1xuICAgICAgICAgICAgICAgICAgICBlbHNlIGlmICh+aXRlbS5pbmRleE9mKHRoaXMucXVlcnkpKVxuICAgICAgICAgICAgICAgICAgICAgICAgY2FzZVNlbnNpdGl2ZS5wdXNoKGl0ZW0pO1xuICAgICAgICAgICAgICAgICAgICBlbHNlXG4gICAgICAgICAgICAgICAgICAgICAgICBjYXNlSW5zZW5zaXRpdmUucHVzaChpdGVtKTtcbiAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICByZXR1cm4gYmVnaW5zd2l0aC5jb25jYXQoY2FzZVNlbnNpdGl2ZSwgY2FzZUluc2Vuc2l0aXZlKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIGl0ZW1zO1xuICAgICAgICAgICAgfVxuICAgICAgICB9LFxuICAgICAgICBoaWdobGlnaHRlcjogZnVuY3Rpb24gKGl0ZW0pIHtcbiAgICAgICAgICAgIHZhciBxdWVyeSA9IHRoaXMucXVlcnkucmVwbGFjZSgvW1xcLVxcW1xcXXt9KCkqKz8uLFxcXFxcXF4kfCNcXHNdL2csICdcXFxcJCYnKTtcbiAgICAgICAgICAgIHJldHVybiBpdGVtLnJlcGxhY2UobmV3IFJlZ0V4cCgnKCcgKyBxdWVyeSArICcpJywgJ2lnJyksIGZ1bmN0aW9uICgkMSwgbWF0Y2gpIHtcbiAgICAgICAgICAgICAgICByZXR1cm4gJzxzdHJvbmc+JyArIG1hdGNoICsgJzwvc3Ryb25nPic7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfSxcbiAgICAgICAgcmVuZGVyOiBmdW5jdGlvbiAoaXRlbXMpIHtcbiAgICAgICAgICAgIHZhciB0aGF0ID0gdGhpcywgZGlzcGxheSwgaXNTdHJpbmcgPSB0eXBlb2YgdGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZCA9PT0gJ3N0cmluZyc7XG5cbiAgICAgICAgICAgIGl0ZW1zID0gJChpdGVtcykubWFwKGZ1bmN0aW9uIChpLCBpdGVtKSB7XG4gICAgICAgICAgICAgICAgaWYgKHR5cGVvZiBpdGVtID09PSAnb2JqZWN0Jykge1xuICAgICAgICAgICAgICAgICAgICBkaXNwbGF5ID0gaXNTdHJpbmcgPyBpdGVtW3RoYXQub3B0aW9ucy5kaXNwbGF5RmllbGRdIDogdGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZChpdGVtKTtcbiAgICAgICAgICAgICAgICAgICAgaSA9ICQodGhhdC5vcHRpb25zLml0ZW0pLmF0dHIoJ2RhdGEtdmFsdWUnLCBpdGVtW3RoYXQub3B0aW9ucy52YWx1ZUZpZWxkXSk7XG4gICAgICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICAgICAgZGlzcGxheSA9IGl0ZW07XG4gICAgICAgICAgICAgICAgICAgIGkgPSAkKHRoYXQub3B0aW9ucy5pdGVtKS5hdHRyKCdkYXRhLXZhbHVlJywgaXRlbSk7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgIGkuZmluZCgnYScpLmh0bWwodGhhdC5oaWdobGlnaHRlcihkaXNwbGF5KSk7XG4gICAgICAgICAgICAgICAgcmV0dXJuIGlbMF07XG4gICAgICAgICAgICB9KTtcblxuICAgICAgICAgICAgaXRlbXMuZmlyc3QoKS5hZGRDbGFzcygnYWN0aXZlJyk7XG5cbiAgICAgICAgICAgIHRoaXMuJG1lbnUuaHRtbChpdGVtcyk7XG4gICAgICAgICAgICByZXR1cm4gdGhpcztcbiAgICAgICAgfSxcbiAgICAgICAgLy8tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS1cbiAgICAgICAgLy8gIEZpbHRlcnMgcmVsZXZlbnQgcmVzdWx0c1xuICAgICAgICAvL1xuICAgICAgICBncmVwcGVyOiBmdW5jdGlvbiAoZGF0YSkge1xuICAgICAgICAgICAgdmFyIHRoYXQgPSB0aGlzLCBpdGVtcywgZGlzcGxheSwgaXNTdHJpbmcgPSB0eXBlb2YgdGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZCA9PT0gJ3N0cmluZyc7XG5cbiAgICAgICAgICAgIGlmIChpc1N0cmluZyAmJiBkYXRhICYmIGRhdGEubGVuZ3RoKSB7XG4gICAgICAgICAgICAgICAgaWYgKGRhdGFbMF0uaGFzT3duUHJvcGVydHkodGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZCkpIHtcbiAgICAgICAgICAgICAgICAgICAgaXRlbXMgPSAkLmdyZXAoZGF0YSwgZnVuY3Rpb24gKGl0ZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIGRpc3BsYXkgPSBpc1N0cmluZyA/IGl0ZW1bdGhhdC5vcHRpb25zLmRpc3BsYXlGaWVsZF0gOiB0aGF0Lm9wdGlvbnMuZGlzcGxheUZpZWxkKGl0ZW0pO1xuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHRoYXQubWF0Y2hlcihkaXNwbGF5KTtcbiAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgfSBlbHNlIGlmICh0eXBlb2YgZGF0YVswXSA9PT0gJ3N0cmluZycpIHtcbiAgICAgICAgICAgICAgICAgICAgaXRlbXMgPSAkLmdyZXAoZGF0YSwgZnVuY3Rpb24gKGl0ZW0pIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVybiB0aGF0Lm1hdGNoZXIoaXRlbSk7XG4gICAgICAgICAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBudWxsO1xuICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgcmV0dXJuIG51bGw7XG4gICAgICAgICAgICB9XG4gICAgICAgICAgICByZXR1cm4gdGhpcy5zb3J0ZXIoaXRlbXMpO1xuICAgICAgICB9LFxuICAgICAgICBuZXh0OiBmdW5jdGlvbiAoZXZlbnQpIHtcbiAgICAgICAgICAgIHZhciBhY3RpdmUgPSB0aGlzLiRtZW51LmZpbmQoJy5hY3RpdmUnKS5yZW1vdmVDbGFzcygnYWN0aXZlJyksXG4gICAgICAgICAgICAgICAgbmV4dCA9IGFjdGl2ZS5uZXh0KCk7XG5cbiAgICAgICAgICAgIGlmICghbmV4dC5sZW5ndGgpIHtcbiAgICAgICAgICAgICAgICBuZXh0ID0gJCh0aGlzLiRtZW51LmZpbmQoJ2xpJylbMF0pO1xuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICBpZiAodGhpcy5vcHRpb25zLnNjcm9sbEJhcikge1xuICAgICAgICAgICAgICAgIHZhciBpbmRleCA9IHRoaXMuJG1lbnUuY2hpbGRyZW4oXCJsaVwiKS5pbmRleChuZXh0KTtcbiAgICAgICAgICAgICAgICBpZiAoaW5kZXggJSA4ID09IDApIHtcbiAgICAgICAgICAgICAgICAgICAgdGhpcy4kbWVudS5zY3JvbGxUb3AoaW5kZXggKiAyNik7XG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICBuZXh0LmFkZENsYXNzKCdhY3RpdmUnKTtcbiAgICAgICAgfSxcbiAgICAgICAgcHJldjogZnVuY3Rpb24gKGV2ZW50KSB7XG4gICAgICAgICAgICB2YXIgYWN0aXZlID0gdGhpcy4kbWVudS5maW5kKCcuYWN0aXZlJykucmVtb3ZlQ2xhc3MoJ2FjdGl2ZScpLFxuICAgICAgICAgICAgICAgIHByZXYgPSBhY3RpdmUucHJldigpO1xuXG4gICAgICAgICAgICBpZiAoIXByZXYubGVuZ3RoKSB7XG4gICAgICAgICAgICAgICAgcHJldiA9IHRoaXMuJG1lbnUuZmluZCgnbGknKS5sYXN0KCk7XG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIGlmICh0aGlzLm9wdGlvbnMuc2Nyb2xsQmFyKSB7XG5cbiAgICAgICAgICAgICAgICB2YXIgJGxpID0gdGhpcy4kbWVudS5jaGlsZHJlbihcImxpXCIpO1xuICAgICAgICAgICAgICAgIHZhciB0b3RhbCA9ICRsaS5sZW5ndGggLSAxO1xuICAgICAgICAgICAgICAgIHZhciBpbmRleCA9ICRsaS5pbmRleChwcmV2KTtcblxuICAgICAgICAgICAgICAgIGlmICgodG90YWwgLSBpbmRleCkgJSA4ID09IDApIHtcbiAgICAgICAgICAgICAgICAgICAgdGhpcy4kbWVudS5zY3JvbGxUb3AoKGluZGV4IC0gNykgKiAyNik7XG4gICAgICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIHByZXYuYWRkQ2xhc3MoJ2FjdGl2ZScpO1xuXG4gICAgICAgIH0sXG4gICAgICAgIGxpc3RlbjogZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgdGhpcy4kZWxlbWVudFxuICAgICAgICAgICAgICAgIC5vbignZm9jdXMnLCAkLnByb3h5KHRoaXMuZm9jdXMsIHRoaXMpKVxuICAgICAgICAgICAgICAgIC5vbignYmx1cicsICQucHJveHkodGhpcy5ibHVyLCB0aGlzKSlcbiAgICAgICAgICAgICAgICAub24oJ2tleXByZXNzJywgJC5wcm94eSh0aGlzLmtleXByZXNzLCB0aGlzKSlcbiAgICAgICAgICAgICAgICAub24oJ2tleXVwJywgJC5wcm94eSh0aGlzLmtleXVwLCB0aGlzKSk7XG5cbiAgICAgICAgICAgIGlmICh0aGlzLmV2ZW50U3VwcG9ydGVkKCdrZXlkb3duJykpIHtcbiAgICAgICAgICAgICAgICB0aGlzLiRlbGVtZW50Lm9uKCdrZXlkb3duJywgJC5wcm94eSh0aGlzLmtleWRvd24sIHRoaXMpKVxuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICB0aGlzLiRtZW51XG4gICAgICAgICAgICAgICAgLm9uKCdjbGljaycsICQucHJveHkodGhpcy5jbGljaywgdGhpcykpXG4gICAgICAgICAgICAgICAgLm9uKCdtb3VzZWVudGVyJywgJ2xpJywgJC5wcm94eSh0aGlzLm1vdXNlZW50ZXIsIHRoaXMpKVxuICAgICAgICAgICAgICAgIC5vbignbW91c2VsZWF2ZScsICdsaScsICQucHJveHkodGhpcy5tb3VzZWxlYXZlLCB0aGlzKSlcbiAgICAgICAgfSxcbiAgICAgICAgbW92ZTogZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgICAgIGlmICghdGhpcy5zaG93bilcbiAgICAgICAgICAgICAgICByZXR1cm5cblxuICAgICAgICAgICAgc3dpdGNoIChlLmtleUNvZGUpIHtcbiAgICAgICAgICAgICAgICBjYXNlIDk6IC8vIHRhYlxuICAgICAgICAgICAgICAgIGNhc2UgMTM6IC8vIGVudGVyXG4gICAgICAgICAgICAgICAgY2FzZSAyNzogLy8gZXNjYXBlXG4gICAgICAgICAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKTtcbiAgICAgICAgICAgICAgICAgICAgYnJlYWtcblxuICAgICAgICAgICAgICAgIGNhc2UgMzg6IC8vIHVwIGFycm93XG4gICAgICAgICAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKVxuICAgICAgICAgICAgICAgICAgICB0aGlzLnByZXYoKVxuICAgICAgICAgICAgICAgICAgICBicmVha1xuXG4gICAgICAgICAgICAgICAgY2FzZSA0MDogLy8gZG93biBhcnJvd1xuICAgICAgICAgICAgICAgICAgICBlLnByZXZlbnREZWZhdWx0KClcbiAgICAgICAgICAgICAgICAgICAgdGhpcy5uZXh0KClcbiAgICAgICAgICAgICAgICAgICAgYnJlYWtcbiAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgZS5zdG9wUHJvcGFnYXRpb24oKTtcbiAgICAgICAgfSxcbiAgICAgICAga2V5ZG93bjogZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgICAgIHRoaXMuc3VwcHJlc3NLZXlQcmVzc1JlcGVhdCA9IH4kLmluQXJyYXkoZS5rZXlDb2RlLCBbNDAsIDM4LCA5LCAxMywgMjddKVxuICAgICAgICAgICAgdGhpcy5tb3ZlKGUpXG4gICAgICAgIH0sXG4gICAgICAgIGtleXByZXNzOiBmdW5jdGlvbiAoZSkge1xuICAgICAgICAgICAgaWYgKHRoaXMuc3VwcHJlc3NLZXlQcmVzc1JlcGVhdClcbiAgICAgICAgICAgICAgICByZXR1cm5cbiAgICAgICAgICAgIHRoaXMubW92ZShlKVxuICAgICAgICB9LFxuICAgICAgICBrZXl1cDogZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgICAgIHN3aXRjaCAoZS5rZXlDb2RlKSB7XG4gICAgICAgICAgICAgICAgY2FzZSA0MDogLy8gZG93biBhcnJvd1xuICAgICAgICAgICAgICAgIGNhc2UgMzg6IC8vIHVwIGFycm93XG4gICAgICAgICAgICAgICAgY2FzZSAxNjogLy8gc2hpZnRcbiAgICAgICAgICAgICAgICBjYXNlIDE3OiAvLyBjdHJsXG4gICAgICAgICAgICAgICAgY2FzZSAxODogLy8gYWx0XG4gICAgICAgICAgICAgICAgICAgIGJyZWFrXG5cbiAgICAgICAgICAgICAgICBjYXNlIDk6IC8vIHRhYlxuICAgICAgICAgICAgICAgIGNhc2UgMTM6IC8vIGVudGVyXG4gICAgICAgICAgICAgICAgICAgIGlmICghdGhpcy5zaG93bilcbiAgICAgICAgICAgICAgICAgICAgICAgIHJldHVyblxuICAgICAgICAgICAgICAgICAgICB0aGlzLnNlbGVjdCgpXG4gICAgICAgICAgICAgICAgICAgIGJyZWFrXG5cbiAgICAgICAgICAgICAgICBjYXNlIDI3OiAvLyBlc2NhcGVcbiAgICAgICAgICAgICAgICAgICAgaWYgKCF0aGlzLnNob3duKVxuICAgICAgICAgICAgICAgICAgICAgICAgcmV0dXJuXG4gICAgICAgICAgICAgICAgICAgIHRoaXMuaGlkZSgpXG4gICAgICAgICAgICAgICAgICAgIGJyZWFrXG5cbiAgICAgICAgICAgICAgICBkZWZhdWx0OlxuICAgICAgICAgICAgICAgICAgICBpZiAodGhpcy5hamF4KVxuICAgICAgICAgICAgICAgICAgICAgICAgdGhpcy5hamF4TG9va3VwKClcbiAgICAgICAgICAgICAgICAgICAgZWxzZVxuICAgICAgICAgICAgICAgICAgICAgICAgdGhpcy5sb29rdXAoKVxuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICBlLnN0b3BQcm9wYWdhdGlvbigpXG4gICAgICAgICAgICBlLnByZXZlbnREZWZhdWx0KClcbiAgICAgICAgfSxcbiAgICAgICAgZm9jdXM6IGZ1bmN0aW9uIChlKSB7XG4gICAgICAgICAgICB0aGlzLmZvY3VzZWQgPSB0cnVlXG4gICAgICAgIH0sXG4gICAgICAgIGJsdXI6IGZ1bmN0aW9uIChlKSB7XG4gICAgICAgICAgICB0aGlzLmZvY3VzZWQgPSBmYWxzZVxuICAgICAgICAgICAgaWYgKCF0aGlzLm1vdXNlZG92ZXIgJiYgdGhpcy5zaG93bilcbiAgICAgICAgICAgICAgICB0aGlzLmhpZGUoKVxuICAgICAgICB9LFxuICAgICAgICBjbGljazogZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgICAgIGUuc3RvcFByb3BhZ2F0aW9uKClcbiAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKVxuICAgICAgICAgICAgdGhpcy5zZWxlY3QoKVxuICAgICAgICAgICAgdGhpcy4kZWxlbWVudC5mb2N1cygpXG4gICAgICAgIH0sXG4gICAgICAgIG1vdXNlZW50ZXI6IGZ1bmN0aW9uIChlKSB7XG4gICAgICAgICAgICB0aGlzLm1vdXNlZG92ZXIgPSB0cnVlXG4gICAgICAgICAgICB0aGlzLiRtZW51LmZpbmQoJy5hY3RpdmUnKS5yZW1vdmVDbGFzcygnYWN0aXZlJylcbiAgICAgICAgICAgICQoZS5jdXJyZW50VGFyZ2V0KS5hZGRDbGFzcygnYWN0aXZlJylcbiAgICAgICAgfSxcbiAgICAgICAgbW91c2VsZWF2ZTogZnVuY3Rpb24gKGUpIHtcbiAgICAgICAgICAgIHRoaXMubW91c2Vkb3ZlciA9IGZhbHNlXG4gICAgICAgICAgICBpZiAoIXRoaXMuZm9jdXNlZCAmJiB0aGlzLnNob3duKVxuICAgICAgICAgICAgICAgIHRoaXMuaGlkZSgpXG4gICAgICAgIH0sXG4gICAgICAgIGRlc3Ryb3k6IGZ1bmN0aW9uKCkge1xuICAgICAgICAgICAgdGhpcy4kZWxlbWVudFxuICAgICAgICAgICAgICAgIC5vZmYoJ2ZvY3VzJywgJC5wcm94eSh0aGlzLmZvY3VzLCB0aGlzKSlcbiAgICAgICAgICAgICAgICAub2ZmKCdibHVyJywgJC5wcm94eSh0aGlzLmJsdXIsIHRoaXMpKVxuICAgICAgICAgICAgICAgIC5vZmYoJ2tleXByZXNzJywgJC5wcm94eSh0aGlzLmtleXByZXNzLCB0aGlzKSlcbiAgICAgICAgICAgICAgICAub2ZmKCdrZXl1cCcsICQucHJveHkodGhpcy5rZXl1cCwgdGhpcykpO1xuXG4gICAgICAgICAgICBpZiAodGhpcy5ldmVudFN1cHBvcnRlZCgna2V5ZG93bicpKSB7XG4gICAgICAgICAgICAgICAgdGhpcy4kZWxlbWVudC5vZmYoJ2tleWRvd24nLCAkLnByb3h5KHRoaXMua2V5ZG93biwgdGhpcykpXG4gICAgICAgICAgICB9XG5cbiAgICAgICAgICAgIHRoaXMuJG1lbnVcbiAgICAgICAgICAgICAgICAub2ZmKCdjbGljaycsICQucHJveHkodGhpcy5jbGljaywgdGhpcykpXG4gICAgICAgICAgICAgICAgLm9mZignbW91c2VlbnRlcicsICdsaScsICQucHJveHkodGhpcy5tb3VzZWVudGVyLCB0aGlzKSlcbiAgICAgICAgICAgICAgICAub2ZmKCdtb3VzZWxlYXZlJywgJ2xpJywgJC5wcm94eSh0aGlzLm1vdXNlbGVhdmUsIHRoaXMpKVxuICAgICAgICAgICAgdGhpcy4kZWxlbWVudC5yZW1vdmVEYXRhKCd0eXBlYWhlYWQnKTtcbiAgICAgICAgfVxuICAgIH07XG5cblxuICAgIC8qIFRZUEVBSEVBRCBQTFVHSU4gREVGSU5JVElPTlxuICAgICAqID09PT09PT09PT09PT09PT09PT09PT09PT09PSAqL1xuXG4gICAgJC5mbi50eXBlYWhlYWQgPSBmdW5jdGlvbiAob3B0aW9uKSB7XG4gICAgICAgIHJldHVybiB0aGlzLmVhY2goZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgdmFyICR0aGlzID0gJCh0aGlzKSxcbiAgICAgICAgICAgICAgICBkYXRhID0gJHRoaXMuZGF0YSgndHlwZWFoZWFkJyksXG4gICAgICAgICAgICAgICAgb3B0aW9ucyA9IHR5cGVvZiBvcHRpb24gPT09ICdvYmplY3QnICYmIG9wdGlvbjtcbiAgICAgICAgICAgIGlmICghZGF0YSlcbiAgICAgICAgICAgICAgICAkdGhpcy5kYXRhKCd0eXBlYWhlYWQnLCAoZGF0YSA9IG5ldyBUeXBlYWhlYWQodGhpcywgb3B0aW9ucykpKTtcbiAgICAgICAgICAgIGlmICh0eXBlb2Ygb3B0aW9uID09PSAnc3RyaW5nJylcbiAgICAgICAgICAgICAgICBkYXRhW29wdGlvbl0oKTtcbiAgICAgICAgfSk7XG4gICAgfTtcblxuICAgICQuZm4udHlwZWFoZWFkLmRlZmF1bHRzID0ge1xuICAgICAgICBzb3VyY2U6IFtdLFxuICAgICAgICBpdGVtczogMTAsXG4gICAgICAgIHNjcm9sbEJhcjogZmFsc2UsXG4gICAgICAgIGFsaWduV2lkdGg6IHRydWUsXG4gICAgICAgIG1lbnU6ICc8dWwgY2xhc3M9XCJ0eXBlYWhlYWQgZHJvcGRvd24tbWVudVwiPjwvdWw+JyxcbiAgICAgICAgaXRlbTogJzxsaT48YSBocmVmPVwiI1wiPjwvYT48L2xpPicsXG4gICAgICAgIHZhbHVlRmllbGQ6ICdpZCcsXG4gICAgICAgIGRpc3BsYXlGaWVsZDogJ25hbWUnLFxuICAgICAgICBvblNlbGVjdDogZnVuY3Rpb24gKCkge1xuICAgICAgICB9LFxuICAgICAgICBhamF4OiB7XG4gICAgICAgICAgICB1cmw6IG51bGwsXG4gICAgICAgICAgICB0aW1lb3V0OiAzMDAsXG4gICAgICAgICAgICBtZXRob2Q6ICdnZXQnLFxuICAgICAgICAgICAgdHJpZ2dlckxlbmd0aDogMSxcbiAgICAgICAgICAgIGxvYWRpbmdDbGFzczogbnVsbCxcbiAgICAgICAgICAgIHByZURpc3BhdGNoOiBudWxsLFxuICAgICAgICAgICAgcHJlUHJvY2VzczogbnVsbFxuICAgICAgICB9XG4gICAgfTtcblxuICAgICQuZm4udHlwZWFoZWFkLkNvbnN0cnVjdG9yID0gVHlwZWFoZWFkO1xuXG4gICAgLyogVFlQRUFIRUFEIERBVEEtQVBJXG4gICAgICogPT09PT09PT09PT09PT09PT09ICovXG5cbiAgICAkKGZ1bmN0aW9uICgpIHtcbiAgICAgICAgJCgnYm9keScpLm9uKCdmb2N1cy50eXBlYWhlYWQuZGF0YS1hcGknLCAnW2RhdGEtcHJvdmlkZT1cInR5cGVhaGVhZFwiXScsIGZ1bmN0aW9uIChlKSB7XG4gICAgICAgICAgICB2YXIgJHRoaXMgPSAkKHRoaXMpO1xuICAgICAgICAgICAgaWYgKCR0aGlzLmRhdGEoJ3R5cGVhaGVhZCcpKVxuICAgICAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKTtcbiAgICAgICAgICAgICR0aGlzLnR5cGVhaGVhZCgkdGhpcy5kYXRhKCkpO1xuICAgICAgICB9KTtcbiAgICB9KTtcblxufSh3aW5kb3cualF1ZXJ5KTsiLCIvKiFcbiAqIGpRdWVyeSBJbnRlcm5hdGlvbmFsaXphdGlvbiBsaWJyYXJ5XG4gKlxuICogQ29weXJpZ2h0IChDKSAyMDEyIFNhbnRob3NoIFRob3R0aW5nYWxcbiAqXG4gKiBqcXVlcnkuaTE4biBpcyBkdWFsIGxpY2Vuc2VkIEdQTHYyIG9yIGxhdGVyIGFuZCBNSVQuIFlvdSBkb24ndCBoYXZlIHRvIGRvXG4gKiBhbnl0aGluZyBzcGVjaWFsIHRvIGNob29zZSBvbmUgbGljZW5zZSBvciB0aGUgb3RoZXIgYW5kIHlvdSBkb24ndCBoYXZlIHRvXG4gKiBub3RpZnkgYW55b25lIHdoaWNoIGxpY2Vuc2UgeW91IGFyZSB1c2luZy4gWW91IGFyZSBmcmVlIHRvIHVzZVxuICogVW5pdmVyc2FsTGFuZ3VhZ2VTZWxlY3RvciBpbiBjb21tZXJjaWFsIHByb2plY3RzIGFzIGxvbmcgYXMgdGhlIGNvcHlyaWdodFxuICogaGVhZGVyIGlzIGxlZnQgaW50YWN0LiBTZWUgZmlsZXMgR1BMLUxJQ0VOU0UgYW5kIE1JVC1MSUNFTlNFIGZvciBkZXRhaWxzLlxuICpcbiAqIEBsaWNlbmNlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbmNlIDIuMCBvciBsYXRlclxuICogQGxpY2VuY2UgTUlUIExpY2Vuc2VcbiAqL1xuXG4oIGZ1bmN0aW9uICggJCApIHtcblx0J3VzZSBzdHJpY3QnO1xuXG5cdHZhciBuYXYsIEkxOE4sXG5cdFx0c2xpY2UgPSBBcnJheS5wcm90b3R5cGUuc2xpY2U7XG5cdC8qKlxuXHQgKiBAY29uc3RydWN0b3Jcblx0ICogQHBhcmFtIHtPYmplY3R9IG9wdGlvbnNcblx0ICovXG5cdEkxOE4gPSBmdW5jdGlvbiAoIG9wdGlvbnMgKSB7XG5cdFx0Ly8gTG9hZCBkZWZhdWx0c1xuXHRcdHRoaXMub3B0aW9ucyA9ICQuZXh0ZW5kKCB7fSwgSTE4Ti5kZWZhdWx0cywgb3B0aW9ucyApO1xuXG5cdFx0dGhpcy5wYXJzZXIgPSB0aGlzLm9wdGlvbnMucGFyc2VyO1xuXHRcdHRoaXMubG9jYWxlID0gdGhpcy5vcHRpb25zLmxvY2FsZTtcblx0XHR0aGlzLm1lc3NhZ2VTdG9yZSA9IHRoaXMub3B0aW9ucy5tZXNzYWdlU3RvcmU7XG5cdFx0dGhpcy5sYW5ndWFnZXMgPSB7fTtcblxuXHRcdHRoaXMuaW5pdCgpO1xuXHR9O1xuXG5cdEkxOE4ucHJvdG90eXBlID0ge1xuXHRcdC8qKlxuXHRcdCAqIEluaXRpYWxpemUgYnkgbG9hZGluZyBsb2NhbGVzIGFuZCBzZXR0aW5nIHVwXG5cdFx0ICogU3RyaW5nLnByb3RvdHlwZS50b0xvY2FsZVN0cmluZyBhbmQgU3RyaW5nLmxvY2FsZS5cblx0XHQgKi9cblx0XHRpbml0OiBmdW5jdGlvbiAoKSB7XG5cdFx0XHR2YXIgaTE4biA9IHRoaXM7XG5cblx0XHRcdC8vIFNldCBsb2NhbGUgb2YgU3RyaW5nIGVudmlyb25tZW50XG5cdFx0XHRTdHJpbmcubG9jYWxlID0gaTE4bi5sb2NhbGU7XG5cblx0XHRcdC8vIE92ZXJyaWRlIFN0cmluZy5sb2NhbGVTdHJpbmcgbWV0aG9kXG5cdFx0XHRTdHJpbmcucHJvdG90eXBlLnRvTG9jYWxlU3RyaW5nID0gZnVuY3Rpb24gKCkge1xuXHRcdFx0XHR2YXIgbG9jYWxlUGFydHMsIGxvY2FsZVBhcnRJbmRleCwgdmFsdWUsIGxvY2FsZSwgZmFsbGJhY2tJbmRleCxcblx0XHRcdFx0XHR0cnlpbmdMb2NhbGUsIG1lc3NhZ2U7XG5cblx0XHRcdFx0dmFsdWUgPSB0aGlzLnZhbHVlT2YoKTtcblx0XHRcdFx0bG9jYWxlID0gaTE4bi5sb2NhbGU7XG5cdFx0XHRcdGZhbGxiYWNrSW5kZXggPSAwO1xuXG5cdFx0XHRcdHdoaWxlICggbG9jYWxlICkge1xuXHRcdFx0XHRcdC8vIEl0ZXJhdGUgdGhyb3VnaCBsb2NhbGVzIHN0YXJ0aW5nIGF0IG1vc3Qtc3BlY2lmaWMgdW50aWxcblx0XHRcdFx0XHQvLyBsb2NhbGl6YXRpb24gaXMgZm91bmQuIEFzIGluIGZpLUxhdG4tRkksIGZpLUxhdG4gYW5kIGZpLlxuXHRcdFx0XHRcdGxvY2FsZVBhcnRzID0gbG9jYWxlLnNwbGl0KCAnLScgKTtcblx0XHRcdFx0XHRsb2NhbGVQYXJ0SW5kZXggPSBsb2NhbGVQYXJ0cy5sZW5ndGg7XG5cblx0XHRcdFx0XHRkbyB7XG5cdFx0XHRcdFx0XHR0cnlpbmdMb2NhbGUgPSBsb2NhbGVQYXJ0cy5zbGljZSggMCwgbG9jYWxlUGFydEluZGV4ICkuam9pbiggJy0nICk7XG5cdFx0XHRcdFx0XHRtZXNzYWdlID0gaTE4bi5tZXNzYWdlU3RvcmUuZ2V0KCB0cnlpbmdMb2NhbGUsIHZhbHVlICk7XG5cblx0XHRcdFx0XHRcdGlmICggbWVzc2FnZSApIHtcblx0XHRcdFx0XHRcdFx0cmV0dXJuIG1lc3NhZ2U7XG5cdFx0XHRcdFx0XHR9XG5cblx0XHRcdFx0XHRcdGxvY2FsZVBhcnRJbmRleC0tO1xuXHRcdFx0XHRcdH0gd2hpbGUgKCBsb2NhbGVQYXJ0SW5kZXggKTtcblxuXHRcdFx0XHRcdGlmICggbG9jYWxlID09PSAnZW4nICkge1xuXHRcdFx0XHRcdFx0YnJlYWs7XG5cdFx0XHRcdFx0fVxuXG5cdFx0XHRcdFx0bG9jYWxlID0gKCAkLmkxOG4uZmFsbGJhY2tzWyBpMThuLmxvY2FsZSBdICYmICQuaTE4bi5mYWxsYmFja3NbIGkxOG4ubG9jYWxlIF1bIGZhbGxiYWNrSW5kZXggXSApIHx8XG5cdFx0XHRcdFx0XHRpMThuLm9wdGlvbnMuZmFsbGJhY2tMb2NhbGU7XG5cdFx0XHRcdFx0JC5pMThuLmxvZyggJ1RyeWluZyBmYWxsYmFjayBsb2NhbGUgZm9yICcgKyBpMThuLmxvY2FsZSArICc6ICcgKyBsb2NhbGUgKTtcblxuXHRcdFx0XHRcdGZhbGxiYWNrSW5kZXgrKztcblx0XHRcdFx0fVxuXG5cdFx0XHRcdC8vIGtleSBub3QgZm91bmRcblx0XHRcdFx0cmV0dXJuICcnO1xuXHRcdFx0fTtcblx0XHR9LFxuXG5cdFx0Lypcblx0XHQgKiBEZXN0cm95IHRoZSBpMThuIGluc3RhbmNlLlxuXHRcdCAqL1xuXHRcdGRlc3Ryb3k6IGZ1bmN0aW9uICgpIHtcblx0XHRcdCQucmVtb3ZlRGF0YSggZG9jdW1lbnQsICdpMThuJyApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBHZW5lcmFsIG1lc3NhZ2UgbG9hZGluZyBBUEkgVGhpcyBjYW4gdGFrZSBhIFVSTCBzdHJpbmcgZm9yXG5cdFx0ICogdGhlIGpzb24gZm9ybWF0dGVkIG1lc3NhZ2VzLiBFeGFtcGxlOlxuXHRcdCAqIDxjb2RlPmxvYWQoJ3BhdGgvdG8vYWxsX2xvY2FsaXphdGlvbnMuanNvbicpOzwvY29kZT5cblx0XHQgKlxuXHRcdCAqIFRvIGxvYWQgYSBsb2NhbGl6YXRpb24gZmlsZSBmb3IgYSBsb2NhbGU6XG5cdFx0ICogPGNvZGU+XG5cdFx0ICogbG9hZCgncGF0aC90by9kZS1tZXNzYWdlcy5qc29uJywgJ2RlJyApO1xuXHRcdCAqIDwvY29kZT5cblx0XHQgKlxuXHRcdCAqIFRvIGxvYWQgYSBsb2NhbGl6YXRpb24gZmlsZSBmcm9tIGEgZGlyZWN0b3J5OlxuXHRcdCAqIDxjb2RlPlxuXHRcdCAqIGxvYWQoJ3BhdGgvdG8vaTE4bi9kaXJlY3RvcnknLCAnZGUnICk7XG5cdFx0ICogPC9jb2RlPlxuXHRcdCAqIFRoZSBhYm92ZSBtZXRob2QgaGFzIHRoZSBhZHZhbnRhZ2Ugb2YgZmFsbGJhY2sgcmVzb2x1dGlvbi5cblx0XHQgKiBpZSwgaXQgd2lsbCBhdXRvbWF0aWNhbGx5IGxvYWQgdGhlIGZhbGxiYWNrIGxvY2FsZXMgZm9yIGRlLlxuXHRcdCAqIEZvciBtb3N0IHVzZWNhc2VzLCB0aGlzIGlzIHRoZSByZWNvbW1lbmRlZCBtZXRob2QuXG5cdFx0ICogSXQgaXMgb3B0aW9uYWwgdG8gaGF2ZSB0cmFpbGluZyBzbGFzaCBhdCBlbmQuXG5cdFx0ICpcblx0XHQgKiBBIGRhdGEgb2JqZWN0IGNvbnRhaW5pbmcgbWVzc2FnZSBrZXktIG1lc3NhZ2UgdHJhbnNsYXRpb24gbWFwcGluZ3Ncblx0XHQgKiBjYW4gYWxzbyBiZSBwYXNzZWQuIEV4YW1wbGU6XG5cdFx0ICogPGNvZGU+XG5cdFx0ICogbG9hZCggeyAnaGVsbG8nIDogJ0hlbGxvJyB9LCBvcHRpb25hbExvY2FsZSApO1xuXHRcdCAqIDwvY29kZT5cblx0XHQgKlxuXHRcdCAqIEEgc291cmNlIG1hcCBjb250YWluaW5nIGtleS12YWx1ZSBwYWlyIG9mIGxhbmd1YWdlbmFtZSBhbmQgbG9jYXRpb25zXG5cdFx0ICogY2FuIGFsc28gYmUgcGFzc2VkLiBFeGFtcGxlOlxuXHRcdCAqIDxjb2RlPlxuXHRcdCAqIGxvYWQoIHtcblx0XHQgKiBibjogJ2kxOG4vYm4uanNvbicsXG5cdFx0ICogaGU6ICdpMThuL2hlLmpzb24nLFxuXHRcdCAqIGVuOiAnaTE4bi9lbi5qc29uJ1xuXHRcdCAqIH0gKVxuXHRcdCAqIDwvY29kZT5cblx0XHQgKlxuXHRcdCAqIElmIHRoZSBkYXRhIGFyZ3VtZW50IGlzIG51bGwvdW5kZWZpbmVkL2ZhbHNlLFxuXHRcdCAqIGFsbCBjYWNoZWQgbWVzc2FnZXMgZm9yIHRoZSBpMThuIGluc3RhbmNlIHdpbGwgZ2V0IHJlc2V0LlxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd8T2JqZWN0fSBzb3VyY2Vcblx0XHQgKiBAcGFyYW0ge3N0cmluZ30gbG9jYWxlIExhbmd1YWdlIHRhZ1xuXHRcdCAqIEByZXR1cm4ge2pRdWVyeS5Qcm9taXNlfVxuXHRcdCAqL1xuXHRcdGxvYWQ6IGZ1bmN0aW9uICggc291cmNlLCBsb2NhbGUgKSB7XG5cdFx0XHR2YXIgZmFsbGJhY2tMb2NhbGVzLCBsb2NJbmRleCwgZmFsbGJhY2tMb2NhbGUsIHNvdXJjZU1hcCA9IHt9O1xuXHRcdFx0aWYgKCAhc291cmNlICYmICFsb2NhbGUgKSB7XG5cdFx0XHRcdHNvdXJjZSA9ICdpMThuLycgKyAkLmkxOG4oKS5sb2NhbGUgKyAnLmpzb24nO1xuXHRcdFx0XHRsb2NhbGUgPSAkLmkxOG4oKS5sb2NhbGU7XG5cdFx0XHR9XG5cdFx0XHRpZiAoIHR5cGVvZiBzb3VyY2UgPT09ICdzdHJpbmcnICYmXG5cdFx0XHRcdHNvdXJjZS5zcGxpdCggJy4nICkucG9wKCkgIT09ICdqc29uJ1xuXHRcdFx0KSB7XG5cdFx0XHRcdC8vIExvYWQgc3BlY2lmaWVkIGxvY2FsZSB0aGVuIGNoZWNrIGZvciBmYWxsYmFja3Mgd2hlbiBkaXJlY3RvcnkgaXMgc3BlY2lmaWVkIGluIGxvYWQoKVxuXHRcdFx0XHRzb3VyY2VNYXBbIGxvY2FsZSBdID0gc291cmNlICsgJy8nICsgbG9jYWxlICsgJy5qc29uJztcblx0XHRcdFx0ZmFsbGJhY2tMb2NhbGVzID0gKCAkLmkxOG4uZmFsbGJhY2tzWyBsb2NhbGUgXSB8fCBbXSApXG5cdFx0XHRcdFx0LmNvbmNhdCggdGhpcy5vcHRpb25zLmZhbGxiYWNrTG9jYWxlICk7XG5cdFx0XHRcdGZvciAoIGxvY0luZGV4IGluIGZhbGxiYWNrTG9jYWxlcyApIHtcblx0XHRcdFx0XHRmYWxsYmFja0xvY2FsZSA9IGZhbGxiYWNrTG9jYWxlc1sgbG9jSW5kZXggXTtcblx0XHRcdFx0XHRzb3VyY2VNYXBbIGZhbGxiYWNrTG9jYWxlIF0gPSBzb3VyY2UgKyAnLycgKyBmYWxsYmFja0xvY2FsZSArICcuanNvbic7XG5cdFx0XHRcdH1cblx0XHRcdFx0cmV0dXJuIHRoaXMubG9hZCggc291cmNlTWFwICk7XG5cdFx0XHR9IGVsc2Uge1xuXHRcdFx0XHRyZXR1cm4gdGhpcy5tZXNzYWdlU3RvcmUubG9hZCggc291cmNlLCBsb2NhbGUgKTtcblx0XHRcdH1cblxuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBEb2VzIHBhcmFtZXRlciBhbmQgbWFnaWMgd29yZCBzdWJzdGl0dXRpb24uXG5cdFx0ICpcblx0XHQgKiBAcGFyYW0ge3N0cmluZ30ga2V5IE1lc3NhZ2Uga2V5XG5cdFx0ICogQHBhcmFtIHtBcnJheX0gcGFyYW1ldGVycyBNZXNzYWdlIHBhcmFtZXRlcnNcblx0XHQgKiBAcmV0dXJuIHtzdHJpbmd9XG5cdFx0ICovXG5cdFx0cGFyc2U6IGZ1bmN0aW9uICgga2V5LCBwYXJhbWV0ZXJzICkge1xuXHRcdFx0dmFyIG1lc3NhZ2UgPSBrZXkudG9Mb2NhbGVTdHJpbmcoKTtcblx0XHRcdC8vIEZJWE1FOiBUaGlzIGNoYW5nZXMgdGhlIHN0YXRlIG9mIHRoZSBJMThOIG9iamVjdCxcblx0XHRcdC8vIHNob3VsZCBwcm9iYWJseSBub3QgY2hhbmdlIHRoZSAndGhpcy5wYXJzZXInIGJ1dCBqdXN0XG5cdFx0XHQvLyBwYXNzIGl0IHRvIHRoZSBwYXJzZXIuXG5cdFx0XHR0aGlzLnBhcnNlci5sYW5ndWFnZSA9ICQuaTE4bi5sYW5ndWFnZXNbICQuaTE4bigpLmxvY2FsZSBdIHx8ICQuaTE4bi5sYW5ndWFnZXNbICdkZWZhdWx0JyBdO1xuXHRcdFx0aWYgKCBtZXNzYWdlID09PSAnJyApIHtcblx0XHRcdFx0bWVzc2FnZSA9IGtleTtcblx0XHRcdH1cblx0XHRcdHJldHVybiB0aGlzLnBhcnNlci5wYXJzZSggbWVzc2FnZSwgcGFyYW1ldGVycyApO1xuXHRcdH1cblx0fTtcblxuXHQvKipcblx0ICogUHJvY2VzcyBhIG1lc3NhZ2UgZnJvbSB0aGUgJC5JMThOIGluc3RhbmNlXG5cdCAqIGZvciB0aGUgY3VycmVudCBkb2N1bWVudCwgc3RvcmVkIGluIGpRdWVyeS5kYXRhKGRvY3VtZW50KS5cblx0ICpcblx0ICogQHBhcmFtIHtzdHJpbmd9IGtleSBLZXkgb2YgdGhlIG1lc3NhZ2UuXG5cdCAqIEBwYXJhbSB7c3RyaW5nfSBwYXJhbTEgW3BhcmFtLi4uXSBWYXJpYWRpYyBsaXN0IG9mIHBhcmFtZXRlcnMgZm9yIHtrZXl9LlxuXHQgKiBAcmV0dXJuIHtzdHJpbmd8JC5JMThOfSBQYXJzZWQgbWVzc2FnZSwgb3IgaWYgbm8ga2V5IHdhcyBnaXZlblxuXHQgKiB0aGUgaW5zdGFuY2Ugb2YgJC5JMThOIGlzIHJldHVybmVkLlxuXHQgKi9cblx0JC5pMThuID0gZnVuY3Rpb24gKCBrZXksIHBhcmFtMSApIHtcblx0XHR2YXIgcGFyYW1ldGVycyxcblx0XHRcdGkxOG4gPSAkLmRhdGEoIGRvY3VtZW50LCAnaTE4bicgKSxcblx0XHRcdG9wdGlvbnMgPSB0eXBlb2Yga2V5ID09PSAnb2JqZWN0JyAmJiBrZXk7XG5cblx0XHQvLyBJZiB0aGUgbG9jYWxlIG9wdGlvbiBmb3IgdGhpcyBjYWxsIGlzIGRpZmZlcmVudCB0aGVuIHRoZSBzZXR1cCBzbyBmYXIsXG5cdFx0Ly8gdXBkYXRlIGl0IGF1dG9tYXRpY2FsbHkuIFRoaXMgZG9lc24ndCBqdXN0IGNoYW5nZSB0aGUgY29udGV4dCBmb3IgdGhpc1xuXHRcdC8vIGNhbGwgYnV0IGZvciBhbGwgZnV0dXJlIGNhbGwgYXMgd2VsbC5cblx0XHQvLyBJZiB0aGVyZSBpcyBubyBpMThuIHNldHVwIHlldCwgZG9uJ3QgZG8gdGhpcy4gSXQgd2lsbCBiZSB0YWtlbiBjYXJlIG9mXG5cdFx0Ly8gYnkgdGhlIGBuZXcgSTE4TmAgY29uc3RydWN0aW9uIGJlbG93LlxuXHRcdC8vIE5PVEU6IEl0IHNob3VsZCBvbmx5IGNoYW5nZSBsYW5ndWFnZSBmb3IgdGhpcyBvbmUgY2FsbC5cblx0XHQvLyBUaGVuIGNhY2hlIGluc3RhbmNlcyBvZiBJMThOIHNvbWV3aGVyZS5cblx0XHRpZiAoIG9wdGlvbnMgJiYgb3B0aW9ucy5sb2NhbGUgJiYgaTE4biAmJiBpMThuLmxvY2FsZSAhPT0gb3B0aW9ucy5sb2NhbGUgKSB7XG5cdFx0XHRTdHJpbmcubG9jYWxlID0gaTE4bi5sb2NhbGUgPSBvcHRpb25zLmxvY2FsZTtcblx0XHR9XG5cblx0XHRpZiAoICFpMThuICkge1xuXHRcdFx0aTE4biA9IG5ldyBJMThOKCBvcHRpb25zICk7XG5cdFx0XHQkLmRhdGEoIGRvY3VtZW50LCAnaTE4bicsIGkxOG4gKTtcblx0XHR9XG5cblx0XHRpZiAoIHR5cGVvZiBrZXkgPT09ICdzdHJpbmcnICkge1xuXHRcdFx0aWYgKCBwYXJhbTEgIT09IHVuZGVmaW5lZCApIHtcblx0XHRcdFx0cGFyYW1ldGVycyA9IHNsaWNlLmNhbGwoIGFyZ3VtZW50cywgMSApO1xuXHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0cGFyYW1ldGVycyA9IFtdO1xuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gaTE4bi5wYXJzZSgga2V5LCBwYXJhbWV0ZXJzICk7XG5cdFx0fSBlbHNlIHtcblx0XHRcdC8vIEZJWE1FOiByZW1vdmUgdGhpcyBmZWF0dXJlL2J1Zy5cblx0XHRcdHJldHVybiBpMThuO1xuXHRcdH1cblx0fTtcblxuXHQkLmZuLmkxOG4gPSBmdW5jdGlvbiAoKSB7XG5cdFx0dmFyIGkxOG4gPSAkLmRhdGEoIGRvY3VtZW50LCAnaTE4bicgKTtcblxuXHRcdGlmICggIWkxOG4gKSB7XG5cdFx0XHRpMThuID0gbmV3IEkxOE4oKTtcblx0XHRcdCQuZGF0YSggZG9jdW1lbnQsICdpMThuJywgaTE4biApO1xuXHRcdH1cblx0XHRTdHJpbmcubG9jYWxlID0gaTE4bi5sb2NhbGU7XG5cdFx0cmV0dXJuIHRoaXMuZWFjaCggZnVuY3Rpb24gKCkge1xuXHRcdFx0dmFyICR0aGlzID0gJCggdGhpcyApLFxuXHRcdFx0XHRtZXNzYWdlS2V5ID0gJHRoaXMuZGF0YSggJ2kxOG4nICksXG5cdFx0XHRcdGxCcmFja2V0LCByQnJhY2tldCwgdHlwZSwga2V5O1xuXG5cdFx0XHRpZiAoIG1lc3NhZ2VLZXkgKSB7XG5cdFx0XHRcdGxCcmFja2V0ID0gbWVzc2FnZUtleS5pbmRleE9mKCAnWycgKTtcblx0XHRcdFx0ckJyYWNrZXQgPSBtZXNzYWdlS2V5LmluZGV4T2YoICddJyApO1xuXHRcdFx0XHRpZiAoIGxCcmFja2V0ICE9PSAtMSAmJiByQnJhY2tldCAhPT0gLTEgJiYgbEJyYWNrZXQgPCByQnJhY2tldCApIHtcblx0XHRcdFx0XHR0eXBlID0gbWVzc2FnZUtleS5zbGljZSggbEJyYWNrZXQgKyAxLCByQnJhY2tldCApO1xuXHRcdFx0XHRcdGtleSA9IG1lc3NhZ2VLZXkuc2xpY2UoIHJCcmFja2V0ICsgMSApO1xuXHRcdFx0XHRcdGlmICggdHlwZSA9PT0gJ2h0bWwnICkge1xuXHRcdFx0XHRcdFx0JHRoaXMuaHRtbCggaTE4bi5wYXJzZSgga2V5ICkgKTtcblx0XHRcdFx0XHR9IGVsc2Uge1xuXHRcdFx0XHRcdFx0JHRoaXMuYXR0ciggdHlwZSwgaTE4bi5wYXJzZSgga2V5ICkgKTtcblx0XHRcdFx0XHR9XG5cdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0JHRoaXMudGV4dCggaTE4bi5wYXJzZSggbWVzc2FnZUtleSApICk7XG5cdFx0XHRcdH1cblx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdCR0aGlzLmZpbmQoICdbZGF0YS1pMThuXScgKS5pMThuKCk7XG5cdFx0XHR9XG5cdFx0fSApO1xuXHR9O1xuXG5cdFN0cmluZy5sb2NhbGUgPSBTdHJpbmcubG9jYWxlIHx8ICQoICdodG1sJyApLmF0dHIoICdsYW5nJyApO1xuXG5cdGlmICggIVN0cmluZy5sb2NhbGUgKSB7XG5cdFx0aWYgKCB0eXBlb2Ygd2luZG93Lm5hdmlnYXRvciAhPT0gdW5kZWZpbmVkICkge1xuXHRcdFx0bmF2ID0gd2luZG93Lm5hdmlnYXRvcjtcblx0XHRcdFN0cmluZy5sb2NhbGUgPSBuYXYubGFuZ3VhZ2UgfHwgbmF2LnVzZXJMYW5ndWFnZSB8fCAnJztcblx0XHR9IGVsc2Uge1xuXHRcdFx0U3RyaW5nLmxvY2FsZSA9ICcnO1xuXHRcdH1cblx0fVxuXG5cdCQuaTE4bi5sYW5ndWFnZXMgPSB7fTtcblx0JC5pMThuLm1lc3NhZ2VTdG9yZSA9ICQuaTE4bi5tZXNzYWdlU3RvcmUgfHwge307XG5cdCQuaTE4bi5wYXJzZXIgPSB7XG5cdFx0Ly8gVGhlIGRlZmF1bHQgcGFyc2VyIG9ubHkgaGFuZGxlcyB2YXJpYWJsZSBzdWJzdGl0dXRpb25cblx0XHRwYXJzZTogZnVuY3Rpb24gKCBtZXNzYWdlLCBwYXJhbWV0ZXJzICkge1xuXHRcdFx0cmV0dXJuIG1lc3NhZ2UucmVwbGFjZSggL1xcJChcXGQrKS9nLCBmdW5jdGlvbiAoIHN0ciwgbWF0Y2ggKSB7XG5cdFx0XHRcdHZhciBpbmRleCA9IHBhcnNlSW50KCBtYXRjaCwgMTAgKSAtIDE7XG5cdFx0XHRcdHJldHVybiBwYXJhbWV0ZXJzWyBpbmRleCBdICE9PSB1bmRlZmluZWQgPyBwYXJhbWV0ZXJzWyBpbmRleCBdIDogJyQnICsgbWF0Y2g7XG5cdFx0XHR9ICk7XG5cdFx0fSxcblx0XHRlbWl0dGVyOiB7fVxuXHR9O1xuXHQkLmkxOG4uZmFsbGJhY2tzID0ge307XG5cdCQuaTE4bi5kZWJ1ZyA9IGZhbHNlO1xuXHQkLmkxOG4ubG9nID0gZnVuY3Rpb24gKCAvKiBhcmd1bWVudHMgKi8gKSB7XG5cdFx0aWYgKCB3aW5kb3cuY29uc29sZSAmJiAkLmkxOG4uZGVidWcgKSB7XG5cdFx0XHR3aW5kb3cuY29uc29sZS5sb2cuYXBwbHkoIHdpbmRvdy5jb25zb2xlLCBhcmd1bWVudHMgKTtcblx0XHR9XG5cdH07XG5cdC8qIFN0YXRpYyBtZW1iZXJzICovXG5cdEkxOE4uZGVmYXVsdHMgPSB7XG5cdFx0bG9jYWxlOiBTdHJpbmcubG9jYWxlLFxuXHRcdGZhbGxiYWNrTG9jYWxlOiAnZW4nLFxuXHRcdHBhcnNlcjogJC5pMThuLnBhcnNlcixcblx0XHRtZXNzYWdlU3RvcmU6ICQuaTE4bi5tZXNzYWdlU3RvcmVcblx0fTtcblxuXHQvLyBFeHBvc2UgY29uc3RydWN0b3Jcblx0JC5pMThuLmNvbnN0cnVjdG9yID0gSTE4Tjtcbn0oIGpRdWVyeSApICk7XG4vKiFcbiAqIGpRdWVyeSBJbnRlcm5hdGlvbmFsaXphdGlvbiBsaWJyYXJ5IC0gTWVzc2FnZSBTdG9yZVxuICpcbiAqIENvcHlyaWdodCAoQykgMjAxMiBTYW50aG9zaCBUaG90dGluZ2FsXG4gKlxuICoganF1ZXJ5LmkxOG4gaXMgZHVhbCBsaWNlbnNlZCBHUEx2MiBvciBsYXRlciBhbmQgTUlULiBZb3UgZG9uJ3QgaGF2ZSB0byBkbyBhbnl0aGluZyBzcGVjaWFsIHRvXG4gKiBjaG9vc2Ugb25lIGxpY2Vuc2Ugb3IgdGhlIG90aGVyIGFuZCB5b3UgZG9uJ3QgaGF2ZSB0byBub3RpZnkgYW55b25lIHdoaWNoIGxpY2Vuc2UgeW91IGFyZSB1c2luZy5cbiAqIFlvdSBhcmUgZnJlZSB0byB1c2UgVW5pdmVyc2FsTGFuZ3VhZ2VTZWxlY3RvciBpbiBjb21tZXJjaWFsIHByb2plY3RzIGFzIGxvbmcgYXMgdGhlIGNvcHlyaWdodFxuICogaGVhZGVyIGlzIGxlZnQgaW50YWN0LiBTZWUgZmlsZXMgR1BMLUxJQ0VOU0UgYW5kIE1JVC1MSUNFTlNFIGZvciBkZXRhaWxzLlxuICpcbiAqIEBsaWNlbmNlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbmNlIDIuMCBvciBsYXRlclxuICogQGxpY2VuY2UgTUlUIExpY2Vuc2VcbiAqL1xuXG4oIGZ1bmN0aW9uICggJCwgd2luZG93LCB1bmRlZmluZWQgKSB7XG5cdCd1c2Ugc3RyaWN0JztcblxuXHR2YXIgTWVzc2FnZVN0b3JlID0gZnVuY3Rpb24gKCkge1xuXHRcdHRoaXMubWVzc2FnZXMgPSB7fTtcblx0XHR0aGlzLnNvdXJjZXMgPSB7fTtcblx0fTtcblxuXHQvKipcblx0ICogU2VlIGh0dHBzOi8vZ2l0aHViLmNvbS93aWtpbWVkaWEvanF1ZXJ5LmkxOG4vd2lraS9TcGVjaWZpY2F0aW9uI3dpa2ktTWVzc2FnZV9GaWxlX0xvYWRpbmdcblx0ICovXG5cdE1lc3NhZ2VTdG9yZS5wcm90b3R5cGUgPSB7XG5cblx0XHQvKipcblx0XHQgKiBHZW5lcmFsIG1lc3NhZ2UgbG9hZGluZyBBUEkgVGhpcyBjYW4gdGFrZSBhIFVSTCBzdHJpbmcgZm9yXG5cdFx0ICogdGhlIGpzb24gZm9ybWF0dGVkIG1lc3NhZ2VzLlxuXHRcdCAqIDxjb2RlPmxvYWQoJ3BhdGgvdG8vYWxsX2xvY2FsaXphdGlvbnMuanNvbicpOzwvY29kZT5cblx0XHQgKlxuXHRcdCAqIFRoaXMgY2FuIGFsc28gbG9hZCBhIGxvY2FsaXphdGlvbiBmaWxlIGZvciBhIGxvY2FsZSA8Y29kZT5cblx0XHQgKiBsb2FkKCAncGF0aC90by9kZS1tZXNzYWdlcy5qc29uJywgJ2RlJyApO1xuXHRcdCAqIDwvY29kZT5cblx0XHQgKiBBIGRhdGEgb2JqZWN0IGNvbnRhaW5pbmcgbWVzc2FnZSBrZXktIG1lc3NhZ2UgdHJhbnNsYXRpb24gbWFwcGluZ3Ncblx0XHQgKiBjYW4gYWxzbyBiZSBwYXNzZWQgRWc6XG5cdFx0ICogPGNvZGU+XG5cdFx0ICogbG9hZCggeyAnaGVsbG8nIDogJ0hlbGxvJyB9LCBvcHRpb25hbExvY2FsZSApO1xuXHRcdCAqIDwvY29kZT4gSWYgdGhlIGRhdGEgYXJndW1lbnQgaXNcblx0XHQgKiBudWxsL3VuZGVmaW5lZC9mYWxzZSxcblx0XHQgKiBhbGwgY2FjaGVkIG1lc3NhZ2VzIGZvciB0aGUgaTE4biBpbnN0YW5jZSB3aWxsIGdldCByZXNldC5cblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7c3RyaW5nfE9iamVjdH0gc291cmNlXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd9IGxvY2FsZSBMYW5ndWFnZSB0YWdcblx0XHQgKiBAcmV0dXJuIHtqUXVlcnkuUHJvbWlzZX1cblx0XHQgKi9cblx0XHRsb2FkOiBmdW5jdGlvbiAoIHNvdXJjZSwgbG9jYWxlICkge1xuXHRcdFx0dmFyIGtleSA9IG51bGwsXG5cdFx0XHRcdGRlZmVycmVkID0gbnVsbCxcblx0XHRcdFx0ZGVmZXJyZWRzID0gW10sXG5cdFx0XHRcdG1lc3NhZ2VTdG9yZSA9IHRoaXM7XG5cblx0XHRcdGlmICggdHlwZW9mIHNvdXJjZSA9PT0gJ3N0cmluZycgKSB7XG5cdFx0XHRcdC8vIFRoaXMgaXMgYSBVUkwgdG8gdGhlIG1lc3NhZ2VzIGZpbGUuXG5cdFx0XHRcdCQuaTE4bi5sb2coICdMb2FkaW5nIG1lc3NhZ2VzIGZyb206ICcgKyBzb3VyY2UgKTtcblx0XHRcdFx0ZGVmZXJyZWQgPSBqc29uTWVzc2FnZUxvYWRlciggc291cmNlIClcblx0XHRcdFx0XHQuZG9uZSggZnVuY3Rpb24gKCBsb2NhbGl6YXRpb24gKSB7XG5cdFx0XHRcdFx0XHRtZXNzYWdlU3RvcmUuc2V0KCBsb2NhbGUsIGxvY2FsaXphdGlvbiApO1xuXHRcdFx0XHRcdH0gKTtcblxuXHRcdFx0XHRyZXR1cm4gZGVmZXJyZWQucHJvbWlzZSgpO1xuXHRcdFx0fVxuXG5cdFx0XHRpZiAoIGxvY2FsZSApIHtcblx0XHRcdFx0Ly8gc291cmNlIGlzIGFuIGtleS12YWx1ZSBwYWlyIG9mIG1lc3NhZ2VzIGZvciBnaXZlbiBsb2NhbGVcblx0XHRcdFx0bWVzc2FnZVN0b3JlLnNldCggbG9jYWxlLCBzb3VyY2UgKTtcblxuXHRcdFx0XHRyZXR1cm4gJC5EZWZlcnJlZCgpLnJlc29sdmUoKTtcblx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdC8vIHNvdXJjZSBpcyBhIGtleS12YWx1ZSBwYWlyIG9mIGxvY2FsZXMgYW5kIHRoZWlyIHNvdXJjZVxuXHRcdFx0XHRmb3IgKCBrZXkgaW4gc291cmNlICkge1xuXHRcdFx0XHRcdGlmICggT2JqZWN0LnByb3RvdHlwZS5oYXNPd25Qcm9wZXJ0eS5jYWxsKCBzb3VyY2UsIGtleSApICkge1xuXHRcdFx0XHRcdFx0bG9jYWxlID0ga2V5O1xuXHRcdFx0XHRcdFx0Ly8gTm8ge2xvY2FsZX0gZ2l2ZW4sIGFzc3VtZSBkYXRhIGlzIGEgZ3JvdXAgb2YgbGFuZ3VhZ2VzLFxuXHRcdFx0XHRcdFx0Ly8gY2FsbCB0aGlzIGZ1bmN0aW9uIGFnYWluIGZvciBlYWNoIGxhbmd1YWdlLlxuXHRcdFx0XHRcdFx0ZGVmZXJyZWRzLnB1c2goIG1lc3NhZ2VTdG9yZS5sb2FkKCBzb3VyY2VbIGtleSBdLCBsb2NhbGUgKSApO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0fVxuXHRcdFx0XHRyZXR1cm4gJC53aGVuLmFwcGx5KCAkLCBkZWZlcnJlZHMgKTtcblx0XHRcdH1cblxuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBTZXQgbWVzc2FnZXMgdG8gdGhlIGdpdmVuIGxvY2FsZS5cblx0XHQgKiBJZiBsb2NhbGUgZXhpc3RzLCBhZGQgbWVzc2FnZXMgdG8gdGhlIGxvY2FsZS5cblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7c3RyaW5nfSBsb2NhbGVcblx0XHQgKiBAcGFyYW0ge09iamVjdH0gbWVzc2FnZXNcblx0XHQgKi9cblx0XHRzZXQ6IGZ1bmN0aW9uICggbG9jYWxlLCBtZXNzYWdlcyApIHtcblx0XHRcdGlmICggIXRoaXMubWVzc2FnZXNbIGxvY2FsZSBdICkge1xuXHRcdFx0XHR0aGlzLm1lc3NhZ2VzWyBsb2NhbGUgXSA9IG1lc3NhZ2VzO1xuXHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0dGhpcy5tZXNzYWdlc1sgbG9jYWxlIF0gPSAkLmV4dGVuZCggdGhpcy5tZXNzYWdlc1sgbG9jYWxlIF0sIG1lc3NhZ2VzICk7XG5cdFx0XHR9XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd9IGxvY2FsZVxuXHRcdCAqIEBwYXJhbSB7c3RyaW5nfSBtZXNzYWdlS2V5XG5cdFx0ICogQHJldHVybiB7Ym9vbGVhbn1cblx0XHQgKi9cblx0XHRnZXQ6IGZ1bmN0aW9uICggbG9jYWxlLCBtZXNzYWdlS2V5ICkge1xuXHRcdFx0cmV0dXJuIHRoaXMubWVzc2FnZXNbIGxvY2FsZSBdICYmIHRoaXMubWVzc2FnZXNbIGxvY2FsZSBdWyBtZXNzYWdlS2V5IF07XG5cdFx0fVxuXHR9O1xuXG5cdGZ1bmN0aW9uIGpzb25NZXNzYWdlTG9hZGVyKCB1cmwgKSB7XG5cdFx0dmFyIGRlZmVycmVkID0gJC5EZWZlcnJlZCgpO1xuXG5cdFx0JC5nZXRKU09OKCB1cmwgKVxuXHRcdFx0LmRvbmUoIGRlZmVycmVkLnJlc29sdmUgKVxuXHRcdFx0LmZhaWwoIGZ1bmN0aW9uICgganF4aHIsIHNldHRpbmdzLCBleGNlcHRpb24gKSB7XG5cdFx0XHRcdCQuaTE4bi5sb2coICdFcnJvciBpbiBsb2FkaW5nIG1lc3NhZ2VzIGZyb20gJyArIHVybCArICcgRXhjZXB0aW9uOiAnICsgZXhjZXB0aW9uICk7XG5cdFx0XHRcdC8vIElnbm9yZSA0MDQgZXhjZXB0aW9uLCBiZWNhdXNlIHdlIGFyZSBoYW5kbGluZyBmYWxsYWJhY2tzIGV4cGxpY2l0bHlcblx0XHRcdFx0ZGVmZXJyZWQucmVzb2x2ZSgpO1xuXHRcdFx0fSApO1xuXG5cdFx0cmV0dXJuIGRlZmVycmVkLnByb21pc2UoKTtcblx0fVxuXG5cdCQuZXh0ZW5kKCAkLmkxOG4ubWVzc2FnZVN0b3JlLCBuZXcgTWVzc2FnZVN0b3JlKCkgKTtcbn0oIGpRdWVyeSwgd2luZG93ICkgKTtcbi8qIVxuICogalF1ZXJ5IEludGVybmF0aW9uYWxpemF0aW9uIGxpYnJhcnlcbiAqXG4gKiBDb3B5cmlnaHQgKEMpIDIwMTIgU2FudGhvc2ggVGhvdHRpbmdhbFxuICpcbiAqIGpxdWVyeS5pMThuIGlzIGR1YWwgbGljZW5zZWQgR1BMdjIgb3IgbGF0ZXIgYW5kIE1JVC4gWW91IGRvbid0IGhhdmUgdG8gZG8gYW55dGhpbmcgc3BlY2lhbCB0b1xuICogY2hvb3NlIG9uZSBsaWNlbnNlIG9yIHRoZSBvdGhlciBhbmQgeW91IGRvbid0IGhhdmUgdG8gbm90aWZ5IGFueW9uZSB3aGljaCBsaWNlbnNlIHlvdSBhcmUgdXNpbmcuXG4gKiBZb3UgYXJlIGZyZWUgdG8gdXNlIFVuaXZlcnNhbExhbmd1YWdlU2VsZWN0b3IgaW4gY29tbWVyY2lhbCBwcm9qZWN0cyBhcyBsb25nIGFzIHRoZSBjb3B5cmlnaHRcbiAqIGhlYWRlciBpcyBsZWZ0IGludGFjdC4gU2VlIGZpbGVzIEdQTC1MSUNFTlNFIGFuZCBNSVQtTElDRU5TRSBmb3IgZGV0YWlscy5cbiAqXG4gKiBAbGljZW5jZSBHTlUgR2VuZXJhbCBQdWJsaWMgTGljZW5jZSAyLjAgb3IgbGF0ZXJcbiAqIEBsaWNlbmNlIE1JVCBMaWNlbnNlXG4gKi9cbiggZnVuY3Rpb24gKCAkLCB1bmRlZmluZWQgKSB7XG5cdCd1c2Ugc3RyaWN0JztcblxuXHQkLmkxOG4gPSAkLmkxOG4gfHwge307XG5cdCQuZXh0ZW5kKCAkLmkxOG4uZmFsbGJhY2tzLCB7XG5cdFx0YWI6IFsgJ3J1JyBdLFxuXHRcdGFjZTogWyAnaWQnIF0sXG5cdFx0YWxuOiBbICdzcScgXSxcblx0XHQvLyBOb3Qgc28gc3RhbmRhcmQgLSBhbHMgaXMgc3VwcG9zZWQgdG8gYmUgVG9zayBBbGJhbmlhbixcblx0XHQvLyBidXQgaW4gV2lraXBlZGlhIGl0J3MgdXNlZCBmb3IgYSBHZXJtYW5pYyBsYW5ndWFnZS5cblx0XHRhbHM6IFsgJ2dzdycsICdkZScgXSxcblx0XHRhbjogWyAnZXMnIF0sXG5cdFx0YW5wOiBbICdoaScgXSxcblx0XHRhcm46IFsgJ2VzJyBdLFxuXHRcdGFyejogWyAnYXInIF0sXG5cdFx0YXY6IFsgJ3J1JyBdLFxuXHRcdGF5OiBbICdlcycgXSxcblx0XHRiYTogWyAncnUnIF0sXG5cdFx0YmFyOiBbICdkZScgXSxcblx0XHQnYmF0LXNtZyc6IFsgJ3NncycsICdsdCcgXSxcblx0XHRiY2M6IFsgJ2ZhJyBdLFxuXHRcdCdiZS14LW9sZCc6IFsgJ2JlLXRhcmFzaycgXSxcblx0XHRiaDogWyAnYmhvJyBdLFxuXHRcdGJqbjogWyAnaWQnIF0sXG5cdFx0Ym06IFsgJ2ZyJyBdLFxuXHRcdGJweTogWyAnYm4nIF0sXG5cdFx0YnFpOiBbICdmYScgXSxcblx0XHRidWc6IFsgJ2lkJyBdLFxuXHRcdCdjYmstemFtJzogWyAnZXMnIF0sXG5cdFx0Y2U6IFsgJ3J1JyBdLFxuXHRcdGNyaDogWyAnY3JoLWxhdG4nIF0sXG5cdFx0J2NyaC1jeXJsJzogWyAncnUnIF0sXG5cdFx0Y3NiOiBbICdwbCcgXSxcblx0XHRjdjogWyAncnUnIF0sXG5cdFx0J2RlLWF0JzogWyAnZGUnIF0sXG5cdFx0J2RlLWNoJzogWyAnZGUnIF0sXG5cdFx0J2RlLWZvcm1hbCc6IFsgJ2RlJyBdLFxuXHRcdGRzYjogWyAnZGUnIF0sXG5cdFx0ZHRwOiBbICdtcycgXSxcblx0XHRlZ2w6IFsgJ2l0JyBdLFxuXHRcdGVtbDogWyAnaXQnIF0sXG5cdFx0ZmY6IFsgJ2ZyJyBdLFxuXHRcdGZpdDogWyAnZmknIF0sXG5cdFx0J2ZpdS12cm8nOiBbICd2cm8nLCAnZXQnIF0sXG5cdFx0ZnJjOiBbICdmcicgXSxcblx0XHRmcnA6IFsgJ2ZyJyBdLFxuXHRcdGZycjogWyAnZGUnIF0sXG5cdFx0ZnVyOiBbICdpdCcgXSxcblx0XHRnYWc6IFsgJ3RyJyBdLFxuXHRcdGdhbjogWyAnZ2FuLWhhbnQnLCAnemgtaGFudCcsICd6aC1oYW5zJyBdLFxuXHRcdCdnYW4taGFucyc6IFsgJ3poLWhhbnMnIF0sXG5cdFx0J2dhbi1oYW50JzogWyAnemgtaGFudCcsICd6aC1oYW5zJyBdLFxuXHRcdGdsOiBbICdwdCcgXSxcblx0XHRnbGs6IFsgJ2ZhJyBdLFxuXHRcdGduOiBbICdlcycgXSxcblx0XHRnc3c6IFsgJ2RlJyBdLFxuXHRcdGhpZjogWyAnaGlmLWxhdG4nIF0sXG5cdFx0aHNiOiBbICdkZScgXSxcblx0XHRodDogWyAnZnInIF0sXG5cdFx0aWk6IFsgJ3poLWNuJywgJ3poLWhhbnMnIF0sXG5cdFx0aW5oOiBbICdydScgXSxcblx0XHRpdTogWyAnaWtlLWNhbnMnIF0sXG5cdFx0anV0OiBbICdkYScgXSxcblx0XHRqdjogWyAnaWQnIF0sXG5cdFx0a2FhOiBbICdray1sYXRuJywgJ2trLWN5cmwnIF0sXG5cdFx0a2JkOiBbICdrYmQtY3lybCcgXSxcblx0XHRraHc6IFsgJ3VyJyBdLFxuXHRcdGtpdTogWyAndHInIF0sXG5cdFx0a2s6IFsgJ2trLWN5cmwnIF0sXG5cdFx0J2trLWFyYWInOiBbICdray1jeXJsJyBdLFxuXHRcdCdray1sYXRuJzogWyAna2stY3lybCcgXSxcblx0XHQna2stY24nOiBbICdray1hcmFiJywgJ2trLWN5cmwnIF0sXG5cdFx0J2trLWt6JzogWyAna2stY3lybCcgXSxcblx0XHQna2stdHInOiBbICdray1sYXRuJywgJ2trLWN5cmwnIF0sXG5cdFx0a2w6IFsgJ2RhJyBdLFxuXHRcdCdrby1rcCc6IFsgJ2tvJyBdLFxuXHRcdGtvaTogWyAncnUnIF0sXG5cdFx0a3JjOiBbICdydScgXSxcblx0XHRrczogWyAna3MtYXJhYicgXSxcblx0XHRrc2g6IFsgJ2RlJyBdLFxuXHRcdGt1OiBbICdrdS1sYXRuJyBdLFxuXHRcdCdrdS1hcmFiJzogWyAnY2tiJyBdLFxuXHRcdGt2OiBbICdydScgXSxcblx0XHRsYWQ6IFsgJ2VzJyBdLFxuXHRcdGxiOiBbICdkZScgXSxcblx0XHRsYmU6IFsgJ3J1JyBdLFxuXHRcdGxlejogWyAncnUnIF0sXG5cdFx0bGk6IFsgJ25sJyBdLFxuXHRcdGxpajogWyAnaXQnIF0sXG5cdFx0bGl2OiBbICdldCcgXSxcblx0XHRsbW86IFsgJ2l0JyBdLFxuXHRcdGxuOiBbICdmcicgXSxcblx0XHRsdGc6IFsgJ2x2JyBdLFxuXHRcdGx6ejogWyAndHInIF0sXG5cdFx0bWFpOiBbICdoaScgXSxcblx0XHQnbWFwLWJtcyc6IFsgJ2p2JywgJ2lkJyBdLFxuXHRcdG1nOiBbICdmcicgXSxcblx0XHRtaHI6IFsgJ3J1JyBdLFxuXHRcdG1pbjogWyAnaWQnIF0sXG5cdFx0bW86IFsgJ3JvJyBdLFxuXHRcdG1yajogWyAncnUnIF0sXG5cdFx0bXdsOiBbICdwdCcgXSxcblx0XHRteXY6IFsgJ3J1JyBdLFxuXHRcdG16bjogWyAnZmEnIF0sXG5cdFx0bmFoOiBbICdlcycgXSxcblx0XHRuYXA6IFsgJ2l0JyBdLFxuXHRcdG5kczogWyAnZGUnIF0sXG5cdFx0J25kcy1ubCc6IFsgJ25sJyBdLFxuXHRcdCdubC1pbmZvcm1hbCc6IFsgJ25sJyBdLFxuXHRcdG5vOiBbICduYicgXSxcblx0XHRvczogWyAncnUnIF0sXG5cdFx0cGNkOiBbICdmcicgXSxcblx0XHRwZGM6IFsgJ2RlJyBdLFxuXHRcdHBkdDogWyAnZGUnIF0sXG5cdFx0cGZsOiBbICdkZScgXSxcblx0XHRwbXM6IFsgJ2l0JyBdLFxuXHRcdHB0OiBbICdwdC1icicgXSxcblx0XHQncHQtYnInOiBbICdwdCcgXSxcblx0XHRxdTogWyAnZXMnIF0sXG5cdFx0cXVnOiBbICdxdScsICdlcycgXSxcblx0XHRyZ246IFsgJ2l0JyBdLFxuXHRcdHJteTogWyAncm8nIF0sXG5cdFx0J3JvYS1ydXAnOiBbICdydXAnIF0sXG5cdFx0cnVlOiBbICd1aycsICdydScgXSxcblx0XHRydXE6IFsgJ3J1cS1sYXRuJywgJ3JvJyBdLFxuXHRcdCdydXEtY3lybCc6IFsgJ21rJyBdLFxuXHRcdCdydXEtbGF0bic6IFsgJ3JvJyBdLFxuXHRcdHNhOiBbICdoaScgXSxcblx0XHRzYWg6IFsgJ3J1JyBdLFxuXHRcdHNjbjogWyAnaXQnIF0sXG5cdFx0c2c6IFsgJ2ZyJyBdLFxuXHRcdHNnczogWyAnbHQnIF0sXG5cdFx0c2xpOiBbICdkZScgXSxcblx0XHRzcjogWyAnc3ItZWMnIF0sXG5cdFx0c3JuOiBbICdubCcgXSxcblx0XHRzdHE6IFsgJ2RlJyBdLFxuXHRcdHN1OiBbICdpZCcgXSxcblx0XHRzemw6IFsgJ3BsJyBdLFxuXHRcdHRjeTogWyAna24nIF0sXG5cdFx0dGc6IFsgJ3RnLWN5cmwnIF0sXG5cdFx0dHQ6IFsgJ3R0LWN5cmwnLCAncnUnIF0sXG5cdFx0J3R0LWN5cmwnOiBbICdydScgXSxcblx0XHR0eTogWyAnZnInIF0sXG5cdFx0dWRtOiBbICdydScgXSxcblx0XHR1ZzogWyAndWctYXJhYicgXSxcblx0XHR1azogWyAncnUnIF0sXG5cdFx0dmVjOiBbICdpdCcgXSxcblx0XHR2ZXA6IFsgJ2V0JyBdLFxuXHRcdHZsczogWyAnbmwnIF0sXG5cdFx0dm1mOiBbICdkZScgXSxcblx0XHR2b3Q6IFsgJ2ZpJyBdLFxuXHRcdHZybzogWyAnZXQnIF0sXG5cdFx0d2E6IFsgJ2ZyJyBdLFxuXHRcdHdvOiBbICdmcicgXSxcblx0XHR3dXU6IFsgJ3poLWhhbnMnIF0sXG5cdFx0eGFsOiBbICdydScgXSxcblx0XHR4bWY6IFsgJ2thJyBdLFxuXHRcdHlpOiBbICdoZScgXSxcblx0XHR6YTogWyAnemgtaGFucycgXSxcblx0XHR6ZWE6IFsgJ25sJyBdLFxuXHRcdHpoOiBbICd6aC1oYW5zJyBdLFxuXHRcdCd6aC1jbGFzc2ljYWwnOiBbICdsemgnIF0sXG5cdFx0J3poLWNuJzogWyAnemgtaGFucycgXSxcblx0XHQnemgtaGFudCc6IFsgJ3poLWhhbnMnIF0sXG5cdFx0J3poLWhrJzogWyAnemgtaGFudCcsICd6aC1oYW5zJyBdLFxuXHRcdCd6aC1taW4tbmFuJzogWyAnbmFuJyBdLFxuXHRcdCd6aC1tbyc6IFsgJ3poLWhrJywgJ3poLWhhbnQnLCAnemgtaGFucycgXSxcblx0XHQnemgtbXknOiBbICd6aC1zZycsICd6aC1oYW5zJyBdLFxuXHRcdCd6aC1zZyc6IFsgJ3poLWhhbnMnIF0sXG5cdFx0J3poLXR3JzogWyAnemgtaGFudCcsICd6aC1oYW5zJyBdLFxuXHRcdCd6aC15dWUnOiBbICd5dWUnIF1cblx0fSApO1xufSggalF1ZXJ5ICkgKTtcbi8qIVxuICogalF1ZXJ5IEludGVybmF0aW9uYWxpemF0aW9uIGxpYnJhcnlcbiAqXG4gKiBDb3B5cmlnaHQgKEMpIDIwMTEtMjAxMyBTYW50aG9zaCBUaG90dGluZ2FsLCBOZWlsIEthbmRhbGdhb25rYXJcbiAqXG4gKiBqcXVlcnkuaTE4biBpcyBkdWFsIGxpY2Vuc2VkIEdQTHYyIG9yIGxhdGVyIGFuZCBNSVQuIFlvdSBkb24ndCBoYXZlIHRvIGRvXG4gKiBhbnl0aGluZyBzcGVjaWFsIHRvIGNob29zZSBvbmUgbGljZW5zZSBvciB0aGUgb3RoZXIgYW5kIHlvdSBkb24ndCBoYXZlIHRvXG4gKiBub3RpZnkgYW55b25lIHdoaWNoIGxpY2Vuc2UgeW91IGFyZSB1c2luZy4gWW91IGFyZSBmcmVlIHRvIHVzZVxuICogVW5pdmVyc2FsTGFuZ3VhZ2VTZWxlY3RvciBpbiBjb21tZXJjaWFsIHByb2plY3RzIGFzIGxvbmcgYXMgdGhlIGNvcHlyaWdodFxuICogaGVhZGVyIGlzIGxlZnQgaW50YWN0LiBTZWUgZmlsZXMgR1BMLUxJQ0VOU0UgYW5kIE1JVC1MSUNFTlNFIGZvciBkZXRhaWxzLlxuICpcbiAqIEBsaWNlbmNlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbmNlIDIuMCBvciBsYXRlclxuICogQGxpY2VuY2UgTUlUIExpY2Vuc2VcbiAqL1xuXG4oIGZ1bmN0aW9uICggJCApIHtcblx0J3VzZSBzdHJpY3QnO1xuXG5cdHZhciBNZXNzYWdlUGFyc2VyID0gZnVuY3Rpb24gKCBvcHRpb25zICkge1xuXHRcdHRoaXMub3B0aW9ucyA9ICQuZXh0ZW5kKCB7fSwgJC5pMThuLnBhcnNlci5kZWZhdWx0cywgb3B0aW9ucyApO1xuXHRcdHRoaXMubGFuZ3VhZ2UgPSAkLmkxOG4ubGFuZ3VhZ2VzWyBTdHJpbmcubG9jYWxlIF0gfHwgJC5pMThuLmxhbmd1YWdlc1sgJ2RlZmF1bHQnIF07XG5cdFx0dGhpcy5lbWl0dGVyID0gJC5pMThuLnBhcnNlci5lbWl0dGVyO1xuXHR9O1xuXG5cdE1lc3NhZ2VQYXJzZXIucHJvdG90eXBlID0ge1xuXG5cdFx0Y29uc3RydWN0b3I6IE1lc3NhZ2VQYXJzZXIsXG5cblx0XHRzaW1wbGVQYXJzZTogZnVuY3Rpb24gKCBtZXNzYWdlLCBwYXJhbWV0ZXJzICkge1xuXHRcdFx0cmV0dXJuIG1lc3NhZ2UucmVwbGFjZSggL1xcJChcXGQrKS9nLCBmdW5jdGlvbiAoIHN0ciwgbWF0Y2ggKSB7XG5cdFx0XHRcdHZhciBpbmRleCA9IHBhcnNlSW50KCBtYXRjaCwgMTAgKSAtIDE7XG5cblx0XHRcdFx0cmV0dXJuIHBhcmFtZXRlcnNbIGluZGV4IF0gIT09IHVuZGVmaW5lZCA/IHBhcmFtZXRlcnNbIGluZGV4IF0gOiAnJCcgKyBtYXRjaDtcblx0XHRcdH0gKTtcblx0XHR9LFxuXG5cdFx0cGFyc2U6IGZ1bmN0aW9uICggbWVzc2FnZSwgcmVwbGFjZW1lbnRzICkge1xuXHRcdFx0aWYgKCBtZXNzYWdlLmluZGV4T2YoICd7eycgKSA8IDAgKSB7XG5cdFx0XHRcdHJldHVybiB0aGlzLnNpbXBsZVBhcnNlKCBtZXNzYWdlLCByZXBsYWNlbWVudHMgKTtcblx0XHRcdH1cblxuXHRcdFx0dGhpcy5lbWl0dGVyLmxhbmd1YWdlID0gJC5pMThuLmxhbmd1YWdlc1sgJC5pMThuKCkubG9jYWxlIF0gfHxcblx0XHRcdFx0JC5pMThuLmxhbmd1YWdlc1sgJ2RlZmF1bHQnIF07XG5cblx0XHRcdHJldHVybiB0aGlzLmVtaXR0ZXIuZW1pdCggdGhpcy5hc3QoIG1lc3NhZ2UgKSwgcmVwbGFjZW1lbnRzICk7XG5cdFx0fSxcblxuXHRcdGFzdDogZnVuY3Rpb24gKCBtZXNzYWdlICkge1xuXHRcdFx0dmFyIHBpcGUsIGNvbG9uLCBiYWNrc2xhc2gsIGFueUNoYXJhY3RlciwgZG9sbGFyLCBkaWdpdHMsIHJlZ3VsYXJMaXRlcmFsLFxuXHRcdFx0XHRyZWd1bGFyTGl0ZXJhbFdpdGhvdXRCYXIsIHJlZ3VsYXJMaXRlcmFsV2l0aG91dFNwYWNlLCBlc2NhcGVkT3JMaXRlcmFsV2l0aG91dEJhcixcblx0XHRcdFx0ZXNjYXBlZE9yUmVndWxhckxpdGVyYWwsIHRlbXBsYXRlQ29udGVudHMsIHRlbXBsYXRlTmFtZSwgb3BlblRlbXBsYXRlLFxuXHRcdFx0XHRjbG9zZVRlbXBsYXRlLCBleHByZXNzaW9uLCBwYXJhbUV4cHJlc3Npb24sIHJlc3VsdCxcblx0XHRcdFx0cG9zID0gMDtcblxuXHRcdFx0Ly8gVHJ5IHBhcnNlcnMgdW50aWwgb25lIHdvcmtzLCBpZiBub25lIHdvcmsgcmV0dXJuIG51bGxcblx0XHRcdGZ1bmN0aW9uIGNob2ljZSggcGFyc2VyU3ludGF4ICkge1xuXHRcdFx0XHRyZXR1cm4gZnVuY3Rpb24gKCkge1xuXHRcdFx0XHRcdHZhciBpLCByZXN1bHQ7XG5cblx0XHRcdFx0XHRmb3IgKCBpID0gMDsgaSA8IHBhcnNlclN5bnRheC5sZW5ndGg7IGkrKyApIHtcblx0XHRcdFx0XHRcdHJlc3VsdCA9IHBhcnNlclN5bnRheFsgaSBdKCk7XG5cblx0XHRcdFx0XHRcdGlmICggcmVzdWx0ICE9PSBudWxsICkge1xuXHRcdFx0XHRcdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdFx0XHRcdFx0fVxuXHRcdFx0XHRcdH1cblxuXHRcdFx0XHRcdHJldHVybiBudWxsO1xuXHRcdFx0XHR9O1xuXHRcdFx0fVxuXG5cdFx0XHQvLyBUcnkgc2V2ZXJhbCBwYXJzZXJTeW50YXgtZXMgaW4gYSByb3cuXG5cdFx0XHQvLyBBbGwgbXVzdCBzdWNjZWVkOyBvdGhlcndpc2UsIHJldHVybiBudWxsLlxuXHRcdFx0Ly8gVGhpcyBpcyB0aGUgb25seSBlYWdlciBvbmUuXG5cdFx0XHRmdW5jdGlvbiBzZXF1ZW5jZSggcGFyc2VyU3ludGF4ICkge1xuXHRcdFx0XHR2YXIgaSwgcmVzLFxuXHRcdFx0XHRcdG9yaWdpbmFsUG9zID0gcG9zLFxuXHRcdFx0XHRcdHJlc3VsdCA9IFtdO1xuXG5cdFx0XHRcdGZvciAoIGkgPSAwOyBpIDwgcGFyc2VyU3ludGF4Lmxlbmd0aDsgaSsrICkge1xuXHRcdFx0XHRcdHJlcyA9IHBhcnNlclN5bnRheFsgaSBdKCk7XG5cblx0XHRcdFx0XHRpZiAoIHJlcyA9PT0gbnVsbCApIHtcblx0XHRcdFx0XHRcdHBvcyA9IG9yaWdpbmFsUG9zO1xuXG5cdFx0XHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdFx0XHR9XG5cblx0XHRcdFx0XHRyZXN1bHQucHVzaCggcmVzICk7XG5cdFx0XHRcdH1cblxuXHRcdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdFx0fVxuXG5cdFx0XHQvLyBSdW4gdGhlIHNhbWUgcGFyc2VyIG92ZXIgYW5kIG92ZXIgdW50aWwgaXQgZmFpbHMuXG5cdFx0XHQvLyBNdXN0IHN1Y2NlZWQgYSBtaW5pbXVtIG9mIG4gdGltZXM7IG90aGVyd2lzZSwgcmV0dXJuIG51bGwuXG5cdFx0XHRmdW5jdGlvbiBuT3JNb3JlKCBuLCBwICkge1xuXHRcdFx0XHRyZXR1cm4gZnVuY3Rpb24gKCkge1xuXHRcdFx0XHRcdHZhciBvcmlnaW5hbFBvcyA9IHBvcyxcblx0XHRcdFx0XHRcdHJlc3VsdCA9IFtdLFxuXHRcdFx0XHRcdFx0cGFyc2VkID0gcCgpO1xuXG5cdFx0XHRcdFx0d2hpbGUgKCBwYXJzZWQgIT09IG51bGwgKSB7XG5cdFx0XHRcdFx0XHRyZXN1bHQucHVzaCggcGFyc2VkICk7XG5cdFx0XHRcdFx0XHRwYXJzZWQgPSBwKCk7XG5cdFx0XHRcdFx0fVxuXG5cdFx0XHRcdFx0aWYgKCByZXN1bHQubGVuZ3RoIDwgbiApIHtcblx0XHRcdFx0XHRcdHBvcyA9IG9yaWdpbmFsUG9zO1xuXG5cdFx0XHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdFx0XHR9XG5cblx0XHRcdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdFx0XHR9O1xuXHRcdFx0fVxuXG5cdFx0XHQvLyBIZWxwZXJzIC0tIGp1c3QgbWFrZSBwYXJzZXJTeW50YXggb3V0IG9mIHNpbXBsZXIgSlMgYnVpbHRpbiB0eXBlc1xuXG5cdFx0XHRmdW5jdGlvbiBtYWtlU3RyaW5nUGFyc2VyKCBzICkge1xuXHRcdFx0XHR2YXIgbGVuID0gcy5sZW5ndGg7XG5cblx0XHRcdFx0cmV0dXJuIGZ1bmN0aW9uICgpIHtcblx0XHRcdFx0XHR2YXIgcmVzdWx0ID0gbnVsbDtcblxuXHRcdFx0XHRcdGlmICggbWVzc2FnZS5zbGljZSggcG9zLCBwb3MgKyBsZW4gKSA9PT0gcyApIHtcblx0XHRcdFx0XHRcdHJlc3VsdCA9IHM7XG5cdFx0XHRcdFx0XHRwb3MgKz0gbGVuO1xuXHRcdFx0XHRcdH1cblxuXHRcdFx0XHRcdHJldHVybiByZXN1bHQ7XG5cdFx0XHRcdH07XG5cdFx0XHR9XG5cblx0XHRcdGZ1bmN0aW9uIG1ha2VSZWdleFBhcnNlciggcmVnZXggKSB7XG5cdFx0XHRcdHJldHVybiBmdW5jdGlvbiAoKSB7XG5cdFx0XHRcdFx0dmFyIG1hdGNoZXMgPSBtZXNzYWdlLnNsaWNlKCBwb3MgKS5tYXRjaCggcmVnZXggKTtcblxuXHRcdFx0XHRcdGlmICggbWF0Y2hlcyA9PT0gbnVsbCApIHtcblx0XHRcdFx0XHRcdHJldHVybiBudWxsO1xuXHRcdFx0XHRcdH1cblxuXHRcdFx0XHRcdHBvcyArPSBtYXRjaGVzWyAwIF0ubGVuZ3RoO1xuXG5cdFx0XHRcdFx0cmV0dXJuIG1hdGNoZXNbIDAgXTtcblx0XHRcdFx0fTtcblx0XHRcdH1cblxuXHRcdFx0cGlwZSA9IG1ha2VTdHJpbmdQYXJzZXIoICd8JyApO1xuXHRcdFx0Y29sb24gPSBtYWtlU3RyaW5nUGFyc2VyKCAnOicgKTtcblx0XHRcdGJhY2tzbGFzaCA9IG1ha2VTdHJpbmdQYXJzZXIoICdcXFxcJyApO1xuXHRcdFx0YW55Q2hhcmFjdGVyID0gbWFrZVJlZ2V4UGFyc2VyKCAvXi4vICk7XG5cdFx0XHRkb2xsYXIgPSBtYWtlU3RyaW5nUGFyc2VyKCAnJCcgKTtcblx0XHRcdGRpZ2l0cyA9IG1ha2VSZWdleFBhcnNlciggL15cXGQrLyApO1xuXHRcdFx0cmVndWxhckxpdGVyYWwgPSBtYWtlUmVnZXhQYXJzZXIoIC9eW157fVxcW1xcXSRcXFxcXS8gKTtcblx0XHRcdHJlZ3VsYXJMaXRlcmFsV2l0aG91dEJhciA9IG1ha2VSZWdleFBhcnNlciggL15bXnt9XFxbXFxdJFxcXFx8XS8gKTtcblx0XHRcdHJlZ3VsYXJMaXRlcmFsV2l0aG91dFNwYWNlID0gbWFrZVJlZ2V4UGFyc2VyKCAvXltee31cXFtcXF0kXFxzXS8gKTtcblxuXHRcdFx0Ly8gVGhlcmUgaXMgYSBnZW5lcmFsIHBhdHRlcm46XG5cdFx0XHQvLyBwYXJzZSBhIHRoaW5nO1xuXHRcdFx0Ly8gaWYgaXQgd29ya2VkLCBhcHBseSB0cmFuc2Zvcm0sXG5cdFx0XHQvLyBvdGhlcndpc2UgcmV0dXJuIG51bGwuXG5cdFx0XHQvLyBCdXQgdXNpbmcgdGhpcyBhcyBhIGNvbWJpbmF0b3Igc2VlbXMgdG8gY2F1c2UgcHJvYmxlbXNcblx0XHRcdC8vIHdoZW4gY29tYmluZWQgd2l0aCBuT3JNb3JlKCkuXG5cdFx0XHQvLyBNYXkgYmUgc29tZSBzY29waW5nIGlzc3VlLlxuXHRcdFx0ZnVuY3Rpb24gdHJhbnNmb3JtKCBwLCBmbiApIHtcblx0XHRcdFx0cmV0dXJuIGZ1bmN0aW9uICgpIHtcblx0XHRcdFx0XHR2YXIgcmVzdWx0ID0gcCgpO1xuXG5cdFx0XHRcdFx0cmV0dXJuIHJlc3VsdCA9PT0gbnVsbCA/IG51bGwgOiBmbiggcmVzdWx0ICk7XG5cdFx0XHRcdH07XG5cdFx0XHR9XG5cblx0XHRcdC8vIFVzZWQgdG8gZGVmaW5lIFwibGl0ZXJhbHNcIiB3aXRoaW4gdGVtcGxhdGUgcGFyYW1ldGVycy4gVGhlIHBpcGVcblx0XHRcdC8vIGNoYXJhY3RlciBpcyB0aGUgcGFyYW1ldGVyIGRlbGltZXRlciwgc28gYnkgZGVmYXVsdFxuXHRcdFx0Ly8gaXQgaXMgbm90IGEgbGl0ZXJhbCBpbiB0aGUgcGFyYW1ldGVyXG5cdFx0XHRmdW5jdGlvbiBsaXRlcmFsV2l0aG91dEJhcigpIHtcblx0XHRcdFx0dmFyIHJlc3VsdCA9IG5Pck1vcmUoIDEsIGVzY2FwZWRPckxpdGVyYWxXaXRob3V0QmFyICkoKTtcblxuXHRcdFx0XHRyZXR1cm4gcmVzdWx0ID09PSBudWxsID8gbnVsbCA6IHJlc3VsdC5qb2luKCAnJyApO1xuXHRcdFx0fVxuXG5cdFx0XHRmdW5jdGlvbiBsaXRlcmFsKCkge1xuXHRcdFx0XHR2YXIgcmVzdWx0ID0gbk9yTW9yZSggMSwgZXNjYXBlZE9yUmVndWxhckxpdGVyYWwgKSgpO1xuXG5cdFx0XHRcdHJldHVybiByZXN1bHQgPT09IG51bGwgPyBudWxsIDogcmVzdWx0LmpvaW4oICcnICk7XG5cdFx0XHR9XG5cblx0XHRcdGZ1bmN0aW9uIGVzY2FwZWRMaXRlcmFsKCkge1xuXHRcdFx0XHR2YXIgcmVzdWx0ID0gc2VxdWVuY2UoIFsgYmFja3NsYXNoLCBhbnlDaGFyYWN0ZXIgXSApO1xuXG5cdFx0XHRcdHJldHVybiByZXN1bHQgPT09IG51bGwgPyBudWxsIDogcmVzdWx0WyAxIF07XG5cdFx0XHR9XG5cblx0XHRcdGNob2ljZSggWyBlc2NhcGVkTGl0ZXJhbCwgcmVndWxhckxpdGVyYWxXaXRob3V0U3BhY2UgXSApO1xuXHRcdFx0ZXNjYXBlZE9yTGl0ZXJhbFdpdGhvdXRCYXIgPSBjaG9pY2UoIFsgZXNjYXBlZExpdGVyYWwsIHJlZ3VsYXJMaXRlcmFsV2l0aG91dEJhciBdICk7XG5cdFx0XHRlc2NhcGVkT3JSZWd1bGFyTGl0ZXJhbCA9IGNob2ljZSggWyBlc2NhcGVkTGl0ZXJhbCwgcmVndWxhckxpdGVyYWwgXSApO1xuXG5cdFx0XHRmdW5jdGlvbiByZXBsYWNlbWVudCgpIHtcblx0XHRcdFx0dmFyIHJlc3VsdCA9IHNlcXVlbmNlKCBbIGRvbGxhciwgZGlnaXRzIF0gKTtcblxuXHRcdFx0XHRpZiAoIHJlc3VsdCA9PT0gbnVsbCApIHtcblx0XHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdHJldHVybiBbICdSRVBMQUNFJywgcGFyc2VJbnQoIHJlc3VsdFsgMSBdLCAxMCApIC0gMSBdO1xuXHRcdFx0fVxuXG5cdFx0XHR0ZW1wbGF0ZU5hbWUgPSB0cmFuc2Zvcm0oXG5cdFx0XHRcdC8vIHNlZSAkd2dMZWdhbFRpdGxlQ2hhcnNcblx0XHRcdFx0Ly8gbm90IGFsbG93aW5nIDogZHVlIHRvIHRoZSBuZWVkIHRvIGNhdGNoIFwiUExVUkFMOiQxXCJcblx0XHRcdFx0bWFrZVJlZ2V4UGFyc2VyKCAvXlsgIVwiJCYnKCkqLC5cXC8wLTk7PT9AQS1aXFxeX2BhLXp+XFx4ODAtXFx4RkYrXFwtXSsvICksXG5cblx0XHRcdFx0ZnVuY3Rpb24gKCByZXN1bHQgKSB7XG5cdFx0XHRcdFx0cmV0dXJuIHJlc3VsdC50b1N0cmluZygpO1xuXHRcdFx0XHR9XG5cdFx0XHQpO1xuXG5cdFx0XHRmdW5jdGlvbiB0ZW1wbGF0ZVBhcmFtKCkge1xuXHRcdFx0XHR2YXIgZXhwcixcblx0XHRcdFx0XHRyZXN1bHQgPSBzZXF1ZW5jZSggWyBwaXBlLCBuT3JNb3JlKCAwLCBwYXJhbUV4cHJlc3Npb24gKSBdICk7XG5cblx0XHRcdFx0aWYgKCByZXN1bHQgPT09IG51bGwgKSB7XG5cdFx0XHRcdFx0cmV0dXJuIG51bGw7XG5cdFx0XHRcdH1cblxuXHRcdFx0XHRleHByID0gcmVzdWx0WyAxIF07XG5cblx0XHRcdFx0Ly8gdXNlIGEgXCJDT05DQVRcIiBvcGVyYXRvciBpZiB0aGVyZSBhcmUgbXVsdGlwbGUgbm9kZXMsXG5cdFx0XHRcdC8vIG90aGVyd2lzZSByZXR1cm4gdGhlIGZpcnN0IG5vZGUsIHJhdy5cblx0XHRcdFx0cmV0dXJuIGV4cHIubGVuZ3RoID4gMSA/IFsgJ0NPTkNBVCcgXS5jb25jYXQoIGV4cHIgKSA6IGV4cHJbIDAgXTtcblx0XHRcdH1cblxuXHRcdFx0ZnVuY3Rpb24gdGVtcGxhdGVXaXRoUmVwbGFjZW1lbnQoKSB7XG5cdFx0XHRcdHZhciByZXN1bHQgPSBzZXF1ZW5jZSggWyB0ZW1wbGF0ZU5hbWUsIGNvbG9uLCByZXBsYWNlbWVudCBdICk7XG5cblx0XHRcdFx0cmV0dXJuIHJlc3VsdCA9PT0gbnVsbCA/IG51bGwgOiBbIHJlc3VsdFsgMCBdLCByZXN1bHRbIDIgXSBdO1xuXHRcdFx0fVxuXG5cdFx0XHRmdW5jdGlvbiB0ZW1wbGF0ZVdpdGhPdXRSZXBsYWNlbWVudCgpIHtcblx0XHRcdFx0dmFyIHJlc3VsdCA9IHNlcXVlbmNlKCBbIHRlbXBsYXRlTmFtZSwgY29sb24sIHBhcmFtRXhwcmVzc2lvbiBdICk7XG5cblx0XHRcdFx0cmV0dXJuIHJlc3VsdCA9PT0gbnVsbCA/IG51bGwgOiBbIHJlc3VsdFsgMCBdLCByZXN1bHRbIDIgXSBdO1xuXHRcdFx0fVxuXG5cdFx0XHR0ZW1wbGF0ZUNvbnRlbnRzID0gY2hvaWNlKCBbXG5cdFx0XHRcdGZ1bmN0aW9uICgpIHtcblx0XHRcdFx0XHR2YXIgcmVzID0gc2VxdWVuY2UoIFtcblx0XHRcdFx0XHRcdC8vIHRlbXBsYXRlcyBjYW4gaGF2ZSBwbGFjZWhvbGRlcnMgZm9yIGR5bmFtaWNcblx0XHRcdFx0XHRcdC8vIHJlcGxhY2VtZW50IGVnOiB7e1BMVVJBTDokMXxvbmUgY2FyfCQxIGNhcnN9fVxuXHRcdFx0XHRcdFx0Ly8gb3Igbm8gcGxhY2Vob2xkZXJzIGVnOlxuXHRcdFx0XHRcdFx0Ly8ge3tHUkFNTUFSOmdlbml0aXZlfHt7U0lURU5BTUV9fX1cblx0XHRcdFx0XHRcdGNob2ljZSggWyB0ZW1wbGF0ZVdpdGhSZXBsYWNlbWVudCwgdGVtcGxhdGVXaXRoT3V0UmVwbGFjZW1lbnQgXSApLFxuXHRcdFx0XHRcdFx0bk9yTW9yZSggMCwgdGVtcGxhdGVQYXJhbSApXG5cdFx0XHRcdFx0XSApO1xuXG5cdFx0XHRcdFx0cmV0dXJuIHJlcyA9PT0gbnVsbCA/IG51bGwgOiByZXNbIDAgXS5jb25jYXQoIHJlc1sgMSBdICk7XG5cdFx0XHRcdH0sXG5cdFx0XHRcdGZ1bmN0aW9uICgpIHtcblx0XHRcdFx0XHR2YXIgcmVzID0gc2VxdWVuY2UoIFsgdGVtcGxhdGVOYW1lLCBuT3JNb3JlKCAwLCB0ZW1wbGF0ZVBhcmFtICkgXSApO1xuXG5cdFx0XHRcdFx0aWYgKCByZXMgPT09IG51bGwgKSB7XG5cdFx0XHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdFx0XHR9XG5cblx0XHRcdFx0XHRyZXR1cm4gWyByZXNbIDAgXSBdLmNvbmNhdCggcmVzWyAxIF0gKTtcblx0XHRcdFx0fVxuXHRcdFx0XSApO1xuXG5cdFx0XHRvcGVuVGVtcGxhdGUgPSBtYWtlU3RyaW5nUGFyc2VyKCAne3snICk7XG5cdFx0XHRjbG9zZVRlbXBsYXRlID0gbWFrZVN0cmluZ1BhcnNlciggJ319JyApO1xuXG5cdFx0XHRmdW5jdGlvbiB0ZW1wbGF0ZSgpIHtcblx0XHRcdFx0dmFyIHJlc3VsdCA9IHNlcXVlbmNlKCBbIG9wZW5UZW1wbGF0ZSwgdGVtcGxhdGVDb250ZW50cywgY2xvc2VUZW1wbGF0ZSBdICk7XG5cblx0XHRcdFx0cmV0dXJuIHJlc3VsdCA9PT0gbnVsbCA/IG51bGwgOiByZXN1bHRbIDEgXTtcblx0XHRcdH1cblxuXHRcdFx0ZXhwcmVzc2lvbiA9IGNob2ljZSggWyB0ZW1wbGF0ZSwgcmVwbGFjZW1lbnQsIGxpdGVyYWwgXSApO1xuXHRcdFx0cGFyYW1FeHByZXNzaW9uID0gY2hvaWNlKCBbIHRlbXBsYXRlLCByZXBsYWNlbWVudCwgbGl0ZXJhbFdpdGhvdXRCYXIgXSApO1xuXG5cdFx0XHRmdW5jdGlvbiBzdGFydCgpIHtcblx0XHRcdFx0dmFyIHJlc3VsdCA9IG5Pck1vcmUoIDAsIGV4cHJlc3Npb24gKSgpO1xuXG5cdFx0XHRcdGlmICggcmVzdWx0ID09PSBudWxsICkge1xuXHRcdFx0XHRcdHJldHVybiBudWxsO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0cmV0dXJuIFsgJ0NPTkNBVCcgXS5jb25jYXQoIHJlc3VsdCApO1xuXHRcdFx0fVxuXG5cdFx0XHRyZXN1bHQgPSBzdGFydCgpO1xuXG5cdFx0XHQvKlxuXHRcdFx0ICogRm9yIHN1Y2Nlc3MsIHRoZSBwb3MgbXVzdCBoYXZlIGdvdHRlbiB0byB0aGUgZW5kIG9mIHRoZSBpbnB1dFxuXHRcdFx0ICogYW5kIHJldHVybmVkIGEgbm9uLW51bGwuXG5cdFx0XHQgKiBuLmIuIFRoaXMgaXMgcGFydCBvZiBsYW5ndWFnZSBpbmZyYXN0cnVjdHVyZSwgc28gd2UgZG8gbm90IHRocm93IGFuIGludGVybmF0aW9uYWxpemFibGUgbWVzc2FnZS5cblx0XHRcdCAqL1xuXHRcdFx0aWYgKCByZXN1bHQgPT09IG51bGwgfHwgcG9zICE9PSBtZXNzYWdlLmxlbmd0aCApIHtcblx0XHRcdFx0dGhyb3cgbmV3IEVycm9yKCAnUGFyc2UgZXJyb3IgYXQgcG9zaXRpb24gJyArIHBvcy50b1N0cmluZygpICsgJyBpbiBpbnB1dDogJyArIG1lc3NhZ2UgKTtcblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIHJlc3VsdDtcblx0XHR9XG5cblx0fTtcblxuXHQkLmV4dGVuZCggJC5pMThuLnBhcnNlciwgbmV3IE1lc3NhZ2VQYXJzZXIoKSApO1xufSggalF1ZXJ5ICkgKTtcbi8qIVxuICogalF1ZXJ5IEludGVybmF0aW9uYWxpemF0aW9uIGxpYnJhcnlcbiAqXG4gKiBDb3B5cmlnaHQgKEMpIDIwMTEtMjAxMyBTYW50aG9zaCBUaG90dGluZ2FsLCBOZWlsIEthbmRhbGdhb25rYXJcbiAqXG4gKiBqcXVlcnkuaTE4biBpcyBkdWFsIGxpY2Vuc2VkIEdQTHYyIG9yIGxhdGVyIGFuZCBNSVQuIFlvdSBkb24ndCBoYXZlIHRvIGRvXG4gKiBhbnl0aGluZyBzcGVjaWFsIHRvIGNob29zZSBvbmUgbGljZW5zZSBvciB0aGUgb3RoZXIgYW5kIHlvdSBkb24ndCBoYXZlIHRvXG4gKiBub3RpZnkgYW55b25lIHdoaWNoIGxpY2Vuc2UgeW91IGFyZSB1c2luZy4gWW91IGFyZSBmcmVlIHRvIHVzZVxuICogVW5pdmVyc2FsTGFuZ3VhZ2VTZWxlY3RvciBpbiBjb21tZXJjaWFsIHByb2plY3RzIGFzIGxvbmcgYXMgdGhlIGNvcHlyaWdodFxuICogaGVhZGVyIGlzIGxlZnQgaW50YWN0LiBTZWUgZmlsZXMgR1BMLUxJQ0VOU0UgYW5kIE1JVC1MSUNFTlNFIGZvciBkZXRhaWxzLlxuICpcbiAqIEBsaWNlbmNlIEdOVSBHZW5lcmFsIFB1YmxpYyBMaWNlbmNlIDIuMCBvciBsYXRlclxuICogQGxpY2VuY2UgTUlUIExpY2Vuc2VcbiAqL1xuXG4oIGZ1bmN0aW9uICggJCApIHtcblx0J3VzZSBzdHJpY3QnO1xuXG5cdHZhciBNZXNzYWdlUGFyc2VyRW1pdHRlciA9IGZ1bmN0aW9uICgpIHtcblx0XHR0aGlzLmxhbmd1YWdlID0gJC5pMThuLmxhbmd1YWdlc1sgU3RyaW5nLmxvY2FsZSBdIHx8ICQuaTE4bi5sYW5ndWFnZXNbICdkZWZhdWx0JyBdO1xuXHR9O1xuXG5cdE1lc3NhZ2VQYXJzZXJFbWl0dGVyLnByb3RvdHlwZSA9IHtcblx0XHRjb25zdHJ1Y3RvcjogTWVzc2FnZVBhcnNlckVtaXR0ZXIsXG5cblx0XHQvKipcblx0XHQgKiAoV2UgcHV0IHRoaXMgbWV0aG9kIGRlZmluaXRpb24gaGVyZSwgYW5kIG5vdCBpbiBwcm90b3R5cGUsIHRvIG1ha2Vcblx0XHQgKiBzdXJlIGl0J3Mgbm90IG92ZXJ3cml0dGVuIGJ5IGFueSBtYWdpYy4pIFdhbGsgZW50aXJlIG5vZGUgc3RydWN0dXJlLFxuXHRcdCAqIGFwcGx5aW5nIHJlcGxhY2VtZW50cyBhbmQgdGVtcGxhdGUgZnVuY3Rpb25zIHdoZW4gYXBwcm9wcmlhdGVcblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7TWl4ZWR9IG5vZGUgYWJzdHJhY3Qgc3ludGF4IHRyZWUgKHRvcCBub2RlIG9yIHN1Ym5vZGUpXG5cdFx0ICogQHBhcmFtIHtBcnJheX0gcmVwbGFjZW1lbnRzIGZvciAkMSwgJDIsIC4uLiAkblxuXHRcdCAqIEByZXR1cm4ge01peGVkfSBzaW5nbGUtc3RyaW5nIG5vZGUgb3IgYXJyYXkgb2Ygbm9kZXMgc3VpdGFibGUgZm9yXG5cdFx0ICogIGpRdWVyeSBhcHBlbmRpbmcuXG5cdFx0ICovXG5cdFx0ZW1pdDogZnVuY3Rpb24gKCBub2RlLCByZXBsYWNlbWVudHMgKSB7XG5cdFx0XHR2YXIgcmV0LCBzdWJub2Rlcywgb3BlcmF0aW9uLFxuXHRcdFx0XHRtZXNzYWdlUGFyc2VyRW1pdHRlciA9IHRoaXM7XG5cblx0XHRcdHN3aXRjaCAoIHR5cGVvZiBub2RlICkge1xuXHRcdFx0Y2FzZSAnc3RyaW5nJzpcblx0XHRcdGNhc2UgJ251bWJlcic6XG5cdFx0XHRcdHJldCA9IG5vZGU7XG5cdFx0XHRcdGJyZWFrO1xuXHRcdFx0Y2FzZSAnb2JqZWN0Jzpcblx0XHRcdFx0Ly8gbm9kZSBpcyBhbiBhcnJheSBvZiBub2Rlc1xuXHRcdFx0XHRzdWJub2RlcyA9ICQubWFwKCBub2RlLnNsaWNlKCAxICksIGZ1bmN0aW9uICggbiApIHtcblx0XHRcdFx0XHRyZXR1cm4gbWVzc2FnZVBhcnNlckVtaXR0ZXIuZW1pdCggbiwgcmVwbGFjZW1lbnRzICk7XG5cdFx0XHRcdH0gKTtcblxuXHRcdFx0XHRvcGVyYXRpb24gPSBub2RlWyAwIF0udG9Mb3dlckNhc2UoKTtcblxuXHRcdFx0XHRpZiAoIHR5cGVvZiBtZXNzYWdlUGFyc2VyRW1pdHRlclsgb3BlcmF0aW9uIF0gPT09ICdmdW5jdGlvbicgKSB7XG5cdFx0XHRcdFx0cmV0ID0gbWVzc2FnZVBhcnNlckVtaXR0ZXJbIG9wZXJhdGlvbiBdKCBzdWJub2RlcywgcmVwbGFjZW1lbnRzICk7XG5cdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0dGhyb3cgbmV3IEVycm9yKCAndW5rbm93biBvcGVyYXRpb24gXCInICsgb3BlcmF0aW9uICsgJ1wiJyApO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0YnJlYWs7XG5cdFx0XHRjYXNlICd1bmRlZmluZWQnOlxuXHRcdFx0XHQvLyBQYXJzaW5nIHRoZSBlbXB0eSBzdHJpbmcgKGFzIGFuIGVudGlyZSBleHByZXNzaW9uLCBvciBhcyBhXG5cdFx0XHRcdC8vIHBhcmFtRXhwcmVzc2lvbiBpbiBhIHRlbXBsYXRlKSByZXN1bHRzIGluIHVuZGVmaW5lZFxuXHRcdFx0XHQvLyBQZXJoYXBzIGEgbW9yZSBjbGV2ZXIgcGFyc2VyIGNhbiBkZXRlY3QgdGhpcywgYW5kIHJldHVybiB0aGVcblx0XHRcdFx0Ly8gZW1wdHkgc3RyaW5nPyBPciBpcyB0aGF0IHVzZWZ1bCBpbmZvcm1hdGlvbj9cblx0XHRcdFx0Ly8gVGhlIGxvZ2ljYWwgdGhpbmcgaXMgcHJvYmFibHkgdG8gcmV0dXJuIHRoZSBlbXB0eSBzdHJpbmcgaGVyZVxuXHRcdFx0XHQvLyB3aGVuIHdlIGVuY291bnRlciB1bmRlZmluZWQuXG5cdFx0XHRcdHJldCA9ICcnO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdGRlZmF1bHQ6XG5cdFx0XHRcdHRocm93IG5ldyBFcnJvciggJ3VuZXhwZWN0ZWQgdHlwZSBpbiBBU1Q6ICcgKyB0eXBlb2Ygbm9kZSApO1xuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gcmV0O1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBQYXJzaW5nIGhhcyBiZWVuIGFwcGxpZWQgZGVwdGgtZmlyc3Qgd2UgY2FuIGFzc3VtZSB0aGF0IGFsbCBub2Rlc1xuXHRcdCAqIGhlcmUgYXJlIHNpbmdsZSBub2RlcyBNdXN0IHJldHVybiBhIHNpbmdsZSBub2RlIHRvIHBhcmVudHMgLS0gYVxuXHRcdCAqIGpRdWVyeSB3aXRoIHN5bnRoZXRpYyBzcGFuIEhvd2V2ZXIsIHVud3JhcCBhbnkgb3RoZXIgc3ludGhldGljIHNwYW5zXG5cdFx0ICogaW4gb3VyIGNoaWxkcmVuIGFuZCBwYXNzIHRoZW0gdXB3YXJkc1xuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtBcnJheX0gbm9kZXMgTWl4ZWQsIHNvbWUgc2luZ2xlIG5vZGVzLCBzb21lIGFycmF5cyBvZiBub2Rlcy5cblx0XHQgKiBAcmV0dXJuIHtzdHJpbmd9XG5cdFx0ICovXG5cdFx0Y29uY2F0OiBmdW5jdGlvbiAoIG5vZGVzICkge1xuXHRcdFx0dmFyIHJlc3VsdCA9ICcnO1xuXG5cdFx0XHQkLmVhY2goIG5vZGVzLCBmdW5jdGlvbiAoIGksIG5vZGUgKSB7XG5cdFx0XHRcdC8vIHN0cmluZ3MsIGludGVnZXJzLCBhbnl0aGluZyBlbHNlXG5cdFx0XHRcdHJlc3VsdCArPSBub2RlO1xuXHRcdFx0fSApO1xuXG5cdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBSZXR1cm4gZXNjYXBlZCByZXBsYWNlbWVudCBvZiBjb3JyZWN0IGluZGV4LCBvciBzdHJpbmcgaWZcblx0XHQgKiB1bmF2YWlsYWJsZS4gTm90ZSB0aGF0IHdlIGV4cGVjdCB0aGUgcGFyc2VkIHBhcmFtZXRlciB0byBiZVxuXHRcdCAqIHplcm8tYmFzZWQuIGkuZS4gJDEgc2hvdWxkIGhhdmUgYmVjb21lIFsgMCBdLiBpZiB0aGUgc3BlY2lmaWVkXG5cdFx0ICogcGFyYW1ldGVyIGlzIG5vdCBmb3VuZCByZXR1cm4gdGhlIHNhbWUgc3RyaW5nIChlLmcuIFwiJDk5XCIgLT5cblx0XHQgKiBwYXJhbWV0ZXIgOTggLT4gbm90IGZvdW5kIC0+IHJldHVybiBcIiQ5OVwiICkgVE9ETyB0aHJvdyBlcnJvciBpZlxuXHRcdCAqIG5vZGVzLmxlbmd0aCA+IDEgP1xuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtBcnJheX0gbm9kZXMgT25lIGVsZW1lbnQsIGludGVnZXIsIG4gPj0gMFxuXHRcdCAqIEBwYXJhbSB7QXJyYXl9IHJlcGxhY2VtZW50cyBmb3IgJDEsICQyLCAuLi4gJG5cblx0XHQgKiBAcmV0dXJuIHtzdHJpbmd9IHJlcGxhY2VtZW50XG5cdFx0ICovXG5cdFx0cmVwbGFjZTogZnVuY3Rpb24gKCBub2RlcywgcmVwbGFjZW1lbnRzICkge1xuXHRcdFx0dmFyIGluZGV4ID0gcGFyc2VJbnQoIG5vZGVzWyAwIF0sIDEwICk7XG5cblx0XHRcdGlmICggaW5kZXggPCByZXBsYWNlbWVudHMubGVuZ3RoICkge1xuXHRcdFx0XHQvLyByZXBsYWNlbWVudCBpcyBub3QgYSBzdHJpbmcsIGRvbid0IHRvdWNoIVxuXHRcdFx0XHRyZXR1cm4gcmVwbGFjZW1lbnRzWyBpbmRleCBdO1xuXHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0Ly8gaW5kZXggbm90IGZvdW5kLCBmYWxsYmFjayB0byBkaXNwbGF5aW5nIHZhcmlhYmxlXG5cdFx0XHRcdHJldHVybiAnJCcgKyAoIGluZGV4ICsgMSApO1xuXHRcdFx0fVxuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBUcmFuc2Zvcm0gcGFyc2VkIHN0cnVjdHVyZSBpbnRvIHBsdXJhbGl6YXRpb24gbi5iLiBUaGUgZmlyc3Qgbm9kZSBtYXlcblx0XHQgKiBiZSBhIG5vbi1pbnRlZ2VyIChmb3IgaW5zdGFuY2UsIGEgc3RyaW5nIHJlcHJlc2VudGluZyBhbiBBcmFiaWNcblx0XHQgKiBudW1iZXIpLiBTbyBjb252ZXJ0IGl0IGJhY2sgd2l0aCB0aGUgY3VycmVudCBsYW5ndWFnZSdzXG5cdFx0ICogY29udmVydE51bWJlci5cblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7QXJyYXl9IG5vZGVzIExpc3QgWyB7U3RyaW5nfE51bWJlcn0sIHtTdHJpbmd9LCB7U3RyaW5nfSAuLi4gXVxuXHRcdCAqIEByZXR1cm4ge3N0cmluZ30gc2VsZWN0ZWQgcGx1cmFsaXplZCBmb3JtIGFjY29yZGluZyB0byBjdXJyZW50XG5cdFx0ICogIGxhbmd1YWdlLlxuXHRcdCAqL1xuXHRcdHBsdXJhbDogZnVuY3Rpb24gKCBub2RlcyApIHtcblx0XHRcdHZhciBjb3VudCA9IHBhcnNlRmxvYXQoIHRoaXMubGFuZ3VhZ2UuY29udmVydE51bWJlciggbm9kZXNbIDAgXSwgMTAgKSApLFxuXHRcdFx0XHRmb3JtcyA9IG5vZGVzLnNsaWNlKCAxICk7XG5cblx0XHRcdHJldHVybiBmb3Jtcy5sZW5ndGggPyB0aGlzLmxhbmd1YWdlLmNvbnZlcnRQbHVyYWwoIGNvdW50LCBmb3JtcyApIDogJyc7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFRyYW5zZm9ybSBwYXJzZWQgc3RydWN0dXJlIGludG8gZ2VuZGVyIFVzYWdlXG5cdFx0ICoge3tnZW5kZXI6Z2VuZGVyfG1hc2N1bGluZXxmZW1pbmluZXxuZXV0cmFsfX0uXG5cdFx0ICpcblx0XHQgKiBAcGFyYW0ge0FycmF5fSBub2RlcyBMaXN0IFsge1N0cmluZ30sIHtTdHJpbmd9LCB7U3RyaW5nfSAsIHtTdHJpbmd9IF1cblx0XHQgKiBAcmV0dXJuIHtzdHJpbmd9IHNlbGVjdGVkIGdlbmRlciBmb3JtIGFjY29yZGluZyB0byBjdXJyZW50IGxhbmd1YWdlXG5cdFx0ICovXG5cdFx0Z2VuZGVyOiBmdW5jdGlvbiAoIG5vZGVzICkge1xuXHRcdFx0dmFyIGdlbmRlciA9IG5vZGVzWyAwIF0sXG5cdFx0XHRcdGZvcm1zID0gbm9kZXMuc2xpY2UoIDEgKTtcblxuXHRcdFx0cmV0dXJuIHRoaXMubGFuZ3VhZ2UuZ2VuZGVyKCBnZW5kZXIsIGZvcm1zICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFRyYW5zZm9ybSBwYXJzZWQgc3RydWN0dXJlIGludG8gZ3JhbW1hciBjb252ZXJzaW9uLiBJbnZva2VkIGJ5XG5cdFx0ICogcHV0dGluZyB7e2dyYW1tYXI6Zm9ybXx3b3JkfX0gaW4gYSBtZXNzYWdlXG5cdFx0ICpcblx0XHQgKiBAcGFyYW0ge0FycmF5fSBub2RlcyBMaXN0IFt7R3JhbW1hciBjYXNlIGVnOiBnZW5pdGl2ZX0sIHtTdHJpbmcgd29yZH1dXG5cdFx0ICogQHJldHVybiB7c3RyaW5nfSBzZWxlY3RlZCBncmFtbWF0aWNhbCBmb3JtIGFjY29yZGluZyB0byBjdXJyZW50XG5cdFx0ICogIGxhbmd1YWdlLlxuXHRcdCAqL1xuXHRcdGdyYW1tYXI6IGZ1bmN0aW9uICggbm9kZXMgKSB7XG5cdFx0XHR2YXIgZm9ybSA9IG5vZGVzWyAwIF0sXG5cdFx0XHRcdHdvcmQgPSBub2Rlc1sgMSBdO1xuXG5cdFx0XHRyZXR1cm4gd29yZCAmJiBmb3JtICYmIHRoaXMubGFuZ3VhZ2UuY29udmVydEdyYW1tYXIoIHdvcmQsIGZvcm0gKTtcblx0XHR9XG5cdH07XG5cblx0JC5leHRlbmQoICQuaTE4bi5wYXJzZXIuZW1pdHRlciwgbmV3IE1lc3NhZ2VQYXJzZXJFbWl0dGVyKCkgKTtcbn0oIGpRdWVyeSApICk7XG4vKmdsb2JhbCBwbHVyYWxSdWxlUGFyc2VyICovXG4oIGZ1bmN0aW9uICggJCApIHtcblx0J3VzZSBzdHJpY3QnO1xuXG5cdC8vIGpzY3M6ZGlzYWJsZVxuXHR2YXIgbGFuZ3VhZ2UgPSB7XG5cdFx0Ly8gQ0xEUiBwbHVyYWwgcnVsZXMgZ2VuZXJhdGVkIHVzaW5nXG5cdFx0Ly8gbGlicy9DTERSUGx1cmFsUnVsZVBhcnNlci90b29scy9QbHVyYWxYTUwySlNPTi5odG1sXG5cdFx0J3BsdXJhbFJ1bGVzJzoge1xuXHRcdFx0J2FmJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdhayc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQnYW0nOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDAgb3IgbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2FyJzoge1xuXHRcdFx0XHQnemVybyc6ICduID0gMCcsXG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJyxcblx0XHRcdFx0J2Zldyc6ICduICUgMTAwID0gMy4uMTAnLFxuXHRcdFx0XHQnbWFueSc6ICduICUgMTAwID0gMTEuLjk5J1xuXHRcdFx0fSxcblx0XHRcdCdhcnMnOiB7XG5cdFx0XHRcdCd6ZXJvJzogJ24gPSAwJyxcblx0XHRcdFx0J29uZSc6ICduID0gMScsXG5cdFx0XHRcdCd0d28nOiAnbiA9IDInLFxuXHRcdFx0XHQnZmV3JzogJ24gJSAxMDAgPSAzLi4xMCcsXG5cdFx0XHRcdCdtYW55JzogJ24gJSAxMDAgPSAxMS4uOTknXG5cdFx0XHR9LFxuXHRcdFx0J2FzJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdhc2EnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2FzdCc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2F6Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdiZSc6IHtcblx0XHRcdFx0J29uZSc6ICduICUgMTAgPSAxIGFuZCBuICUgMTAwICE9IDExJyxcblx0XHRcdFx0J2Zldyc6ICduICUgMTAgPSAyLi40IGFuZCBuICUgMTAwICE9IDEyLi4xNCcsXG5cdFx0XHRcdCdtYW55JzogJ24gJSAxMCA9IDAgb3IgbiAlIDEwID0gNS4uOSBvciBuICUgMTAwID0gMTEuLjE0J1xuXHRcdFx0fSxcblx0XHRcdCdiZW0nOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2Jleic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnYmcnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2JoJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAwLi4xJ1xuXHRcdFx0fSxcblx0XHRcdCdibSc6IHt9LFxuXHRcdFx0J2JuJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdibyc6IHt9LFxuXHRcdFx0J2JyJzoge1xuXHRcdFx0XHQnb25lJzogJ24gJSAxMCA9IDEgYW5kIG4gJSAxMDAgIT0gMTEsNzEsOTEnLFxuXHRcdFx0XHQndHdvJzogJ24gJSAxMCA9IDIgYW5kIG4gJSAxMDAgIT0gMTIsNzIsOTInLFxuXHRcdFx0XHQnZmV3JzogJ24gJSAxMCA9IDMuLjQsOSBhbmQgbiAlIDEwMCAhPSAxMC4uMTksNzAuLjc5LDkwLi45OScsXG5cdFx0XHRcdCdtYW55JzogJ24gIT0gMCBhbmQgbiAlIDEwMDAwMDAgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdicngnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2JzJzoge1xuXHRcdFx0XHQnb25lJzogJ3YgPSAwIGFuZCBpICUgMTAgPSAxIGFuZCBpICUgMTAwICE9IDExIG9yIGYgJSAxMCA9IDEgYW5kIGYgJSAxMDAgIT0gMTEnLFxuXHRcdFx0XHQnZmV3JzogJ3YgPSAwIGFuZCBpICUgMTAgPSAyLi40IGFuZCBpICUgMTAwICE9IDEyLi4xNCBvciBmICUgMTAgPSAyLi40IGFuZCBmICUgMTAwICE9IDEyLi4xNCdcblx0XHRcdH0sXG5cdFx0XHQnY2EnOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdjZSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnY2dnJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdjaHInOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2NrYic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnY3MnOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJyxcblx0XHRcdFx0J2Zldyc6ICdpID0gMi4uNCBhbmQgdiA9IDAnLFxuXHRcdFx0XHQnbWFueSc6ICd2ICE9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2N5Jzoge1xuXHRcdFx0XHQnemVybyc6ICduID0gMCcsXG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJyxcblx0XHRcdFx0J2Zldyc6ICduID0gMycsXG5cdFx0XHRcdCdtYW55JzogJ24gPSA2J1xuXHRcdFx0fSxcblx0XHRcdCdkYSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSBvciB0ICE9IDAgYW5kIGkgPSAwLDEnXG5cdFx0XHR9LFxuXHRcdFx0J2RlJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQnZHNiJzoge1xuXHRcdFx0XHQnb25lJzogJ3YgPSAwIGFuZCBpICUgMTAwID0gMSBvciBmICUgMTAwID0gMScsXG5cdFx0XHRcdCd0d28nOiAndiA9IDAgYW5kIGkgJSAxMDAgPSAyIG9yIGYgJSAxMDAgPSAyJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwMCA9IDMuLjQgb3IgZiAlIDEwMCA9IDMuLjQnXG5cdFx0XHR9LFxuXHRcdFx0J2R2Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdkeic6IHt9LFxuXHRcdFx0J2VlJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdlbCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnZW4nOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdlbyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnZXMnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2V0Jzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQnZXUnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2ZhJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdmZic6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMCwxJ1xuXHRcdFx0fSxcblx0XHRcdCdmaSc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2ZpbCc6IHtcblx0XHRcdFx0J29uZSc6ICd2ID0gMCBhbmQgaSA9IDEsMiwzIG9yIHYgPSAwIGFuZCBpICUgMTAgIT0gNCw2LDkgb3IgdiAhPSAwIGFuZCBmICUgMTAgIT0gNCw2LDknXG5cdFx0XHR9LFxuXHRcdFx0J2ZvJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdmcic6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMCwxJ1xuXHRcdFx0fSxcblx0XHRcdCdmdXInOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2Z5Jzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQnZ2EnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJyxcblx0XHRcdFx0J2Zldyc6ICduID0gMy4uNicsXG5cdFx0XHRcdCdtYW55JzogJ24gPSA3Li4xMCdcblx0XHRcdH0sXG5cdFx0XHQnZ2QnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEsMTEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyLDEyJyxcblx0XHRcdFx0J2Zldyc6ICduID0gMy4uMTAsMTMuLjE5J1xuXHRcdFx0fSxcblx0XHRcdCdnbCc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2dzdyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnZ3UnOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDAgb3IgbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2d1dyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQnZ3YnOiB7XG5cdFx0XHRcdCdvbmUnOiAndiA9IDAgYW5kIGkgJSAxMCA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ3YgPSAwIGFuZCBpICUgMTAgPSAyJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwMCA9IDAsMjAsNDAsNjAsODAnLFxuXHRcdFx0XHQnbWFueSc6ICd2ICE9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2hhJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdoYXcnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2hlJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCcsXG5cdFx0XHRcdCd0d28nOiAnaSA9IDIgYW5kIHYgPSAwJyxcblx0XHRcdFx0J21hbnknOiAndiA9IDAgYW5kIG4gIT0gMC4uMTAgYW5kIG4gJSAxMCA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2hpJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdocic6IHtcblx0XHRcdFx0J29uZSc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMSBhbmQgaSAlIDEwMCAhPSAxMSBvciBmICUgMTAgPSAxIGFuZCBmICUgMTAwICE9IDExJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMi4uNCBhbmQgaSAlIDEwMCAhPSAxMi4uMTQgb3IgZiAlIDEwID0gMi4uNCBhbmQgZiAlIDEwMCAhPSAxMi4uMTQnXG5cdFx0XHR9LFxuXHRcdFx0J2hzYic6IHtcblx0XHRcdFx0J29uZSc6ICd2ID0gMCBhbmQgaSAlIDEwMCA9IDEgb3IgZiAlIDEwMCA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ3YgPSAwIGFuZCBpICUgMTAwID0gMiBvciBmICUgMTAwID0gMicsXG5cdFx0XHRcdCdmZXcnOiAndiA9IDAgYW5kIGkgJSAxMDAgPSAzLi40IG9yIGYgJSAxMDAgPSAzLi40J1xuXHRcdFx0fSxcblx0XHRcdCdodSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnaHknOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDAsMSdcblx0XHRcdH0sXG5cdFx0XHQnaWQnOiB7fSxcblx0XHRcdCdpZyc6IHt9LFxuXHRcdFx0J2lpJzoge30sXG5cdFx0XHQnaW4nOiB7fSxcblx0XHRcdCdpcyc6IHtcblx0XHRcdFx0J29uZSc6ICd0ID0gMCBhbmQgaSAlIDEwID0gMSBhbmQgaSAlIDEwMCAhPSAxMSBvciB0ICE9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2l0Jzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQnaXUnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdpdyc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnLFxuXHRcdFx0XHQndHdvJzogJ2kgPSAyIGFuZCB2ID0gMCcsXG5cdFx0XHRcdCdtYW55JzogJ3YgPSAwIGFuZCBuICE9IDAuLjEwIGFuZCBuICUgMTAgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdqYSc6IHt9LFxuXHRcdFx0J2pibyc6IHt9LFxuXHRcdFx0J2pnbyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnamknOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdqbWMnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2p2Jzoge30sXG5cdFx0XHQnancnOiB7fSxcblx0XHRcdCdrYSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna2FiJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwLDEnXG5cdFx0XHR9LFxuXHRcdFx0J2thaic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna2NnJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdrZGUnOiB7fSxcblx0XHRcdCdrZWEnOiB7fSxcblx0XHRcdCdrayc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna2tqJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdrbCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna20nOiB7fSxcblx0XHRcdCdrbic6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMCBvciBuID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna28nOiB7fSxcblx0XHRcdCdrcyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna3NiJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdrc2gnOiB7XG5cdFx0XHRcdCd6ZXJvJzogJ24gPSAwJyxcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQna3UnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2t3Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJyxcblx0XHRcdFx0J3R3byc6ICduID0gMidcblx0XHRcdH0sXG5cdFx0XHQna3knOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2xhZyc6IHtcblx0XHRcdFx0J3plcm8nOiAnbiA9IDAnLFxuXHRcdFx0XHQnb25lJzogJ2kgPSAwLDEgYW5kIG4gIT0gMCdcblx0XHRcdH0sXG5cdFx0XHQnbGInOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J2xnJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdsa3QnOiB7fSxcblx0XHRcdCdsbic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQnbG8nOiB7fSxcblx0XHRcdCdsdCc6IHtcblx0XHRcdFx0J29uZSc6ICduICUgMTAgPSAxIGFuZCBuICUgMTAwICE9IDExLi4xOScsXG5cdFx0XHRcdCdmZXcnOiAnbiAlIDEwID0gMi4uOSBhbmQgbiAlIDEwMCAhPSAxMS4uMTknLFxuXHRcdFx0XHQnbWFueSc6ICdmICE9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J2x2Jzoge1xuXHRcdFx0XHQnemVybyc6ICduICUgMTAgPSAwIG9yIG4gJSAxMDAgPSAxMS4uMTkgb3IgdiA9IDIgYW5kIGYgJSAxMDAgPSAxMS4uMTknLFxuXHRcdFx0XHQnb25lJzogJ24gJSAxMCA9IDEgYW5kIG4gJSAxMDAgIT0gMTEgb3IgdiA9IDIgYW5kIGYgJSAxMCA9IDEgYW5kIGYgJSAxMDAgIT0gMTEgb3IgdiAhPSAyIGFuZCBmICUgMTAgPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdtYXMnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J21nJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAwLi4xJ1xuXHRcdFx0fSxcblx0XHRcdCdtZ28nOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J21rJzoge1xuXHRcdFx0XHQnb25lJzogJ3YgPSAwIGFuZCBpICUgMTAgPSAxIG9yIGYgJSAxMCA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J21sJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdtbic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnbW8nOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJyxcblx0XHRcdFx0J2Zldyc6ICd2ICE9IDAgb3IgbiA9IDAgb3IgbiAhPSAxIGFuZCBuICUgMTAwID0gMS4uMTknXG5cdFx0XHR9LFxuXHRcdFx0J21yJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdtcyc6IHt9LFxuXHRcdFx0J210Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJyxcblx0XHRcdFx0J2Zldyc6ICduID0gMCBvciBuICUgMTAwID0gMi4uMTAnLFxuXHRcdFx0XHQnbWFueSc6ICduICUgMTAwID0gMTEuLjE5J1xuXHRcdFx0fSxcblx0XHRcdCdteSc6IHt9LFxuXHRcdFx0J25haCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnbmFxJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJyxcblx0XHRcdFx0J3R3byc6ICduID0gMidcblx0XHRcdH0sXG5cdFx0XHQnbmInOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J25kJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCduZSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnbmwnOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdubic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnbm5oJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdubyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnbnFvJzoge30sXG5cdFx0XHQnbnInOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J25zbyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQnbnknOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J255bic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnb20nOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J29yJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdvcyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQncGEnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDAuLjEnXG5cdFx0XHR9LFxuXHRcdFx0J3BhcCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQncGwnOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMi4uNCBhbmQgaSAlIDEwMCAhPSAxMi4uMTQnLFxuXHRcdFx0XHQnbWFueSc6ICd2ID0gMCBhbmQgaSAhPSAxIGFuZCBpICUgMTAgPSAwLi4xIG9yIHYgPSAwIGFuZCBpICUgMTAgPSA1Li45IG9yIHYgPSAwIGFuZCBpICUgMTAwID0gMTIuLjE0J1xuXHRcdFx0fSxcblx0XHRcdCdwcmcnOiB7XG5cdFx0XHRcdCd6ZXJvJzogJ24gJSAxMCA9IDAgb3IgbiAlIDEwMCA9IDExLi4xOSBvciB2ID0gMiBhbmQgZiAlIDEwMCA9IDExLi4xOScsXG5cdFx0XHRcdCdvbmUnOiAnbiAlIDEwID0gMSBhbmQgbiAlIDEwMCAhPSAxMSBvciB2ID0gMiBhbmQgZiAlIDEwID0gMSBhbmQgZiAlIDEwMCAhPSAxMSBvciB2ICE9IDIgYW5kIGYgJSAxMCA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3BzJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdwdCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMiBhbmQgbiAhPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdwdC1QVCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J3JtJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdybyc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnLFxuXHRcdFx0XHQnZmV3JzogJ3YgIT0gMCBvciBuID0gMCBvciBuICE9IDEgYW5kIG4gJSAxMDAgPSAxLi4xOSdcblx0XHRcdH0sXG5cdFx0XHQncm9mJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdyb290Jzoge30sXG5cdFx0XHQncnUnOiB7XG5cdFx0XHRcdCdvbmUnOiAndiA9IDAgYW5kIGkgJSAxMCA9IDEgYW5kIGkgJSAxMDAgIT0gMTEnLFxuXHRcdFx0XHQnZmV3JzogJ3YgPSAwIGFuZCBpICUgMTAgPSAyLi40IGFuZCBpICUgMTAwICE9IDEyLi4xNCcsXG5cdFx0XHRcdCdtYW55JzogJ3YgPSAwIGFuZCBpICUgMTAgPSAwIG9yIHYgPSAwIGFuZCBpICUgMTAgPSA1Li45IG9yIHYgPSAwIGFuZCBpICUgMTAwID0gMTEuLjE0J1xuXHRcdFx0fSxcblx0XHRcdCdyd2snOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3NhaCc6IHt9LFxuXHRcdFx0J3NhcSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnc2RoJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdzZSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMScsXG5cdFx0XHRcdCd0d28nOiAnbiA9IDInXG5cdFx0XHR9LFxuXHRcdFx0J3NlaCc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnc2VzJzoge30sXG5cdFx0XHQnc2cnOiB7fSxcblx0XHRcdCdzaCc6IHtcblx0XHRcdFx0J29uZSc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMSBhbmQgaSAlIDEwMCAhPSAxMSBvciBmICUgMTAgPSAxIGFuZCBmICUgMTAwICE9IDExJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMi4uNCBhbmQgaSAlIDEwMCAhPSAxMi4uMTQgb3IgZiAlIDEwID0gMi4uNCBhbmQgZiAlIDEwMCAhPSAxMi4uMTQnXG5cdFx0XHR9LFxuXHRcdFx0J3NoaSc6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMCBvciBuID0gMScsXG5cdFx0XHRcdCdmZXcnOiAnbiA9IDIuLjEwJ1xuXHRcdFx0fSxcblx0XHRcdCdzaSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMCwxIG9yIGkgPSAwIGFuZCBmID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnc2snOiB7XG5cdFx0XHRcdCdvbmUnOiAnaSA9IDEgYW5kIHYgPSAwJyxcblx0XHRcdFx0J2Zldyc6ICdpID0gMi4uNCBhbmQgdiA9IDAnLFxuXHRcdFx0XHQnbWFueSc6ICd2ICE9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J3NsJzoge1xuXHRcdFx0XHQnb25lJzogJ3YgPSAwIGFuZCBpICUgMTAwID0gMScsXG5cdFx0XHRcdCd0d28nOiAndiA9IDAgYW5kIGkgJSAxMDAgPSAyJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwMCA9IDMuLjQgb3IgdiAhPSAwJ1xuXHRcdFx0fSxcblx0XHRcdCdzbWEnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdzbWknOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdzbWonOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdzbW4nOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdzbXMnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnLFxuXHRcdFx0XHQndHdvJzogJ24gPSAyJ1xuXHRcdFx0fSxcblx0XHRcdCdzbic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQnc28nOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3NxJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdzcic6IHtcblx0XHRcdFx0J29uZSc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMSBhbmQgaSAlIDEwMCAhPSAxMSBvciBmICUgMTAgPSAxIGFuZCBmICUgMTAwICE9IDExJyxcblx0XHRcdFx0J2Zldyc6ICd2ID0gMCBhbmQgaSAlIDEwID0gMi4uNCBhbmQgaSAlIDEwMCAhPSAxMi4uMTQgb3IgZiAlIDEwID0gMi4uNCBhbmQgZiAlIDEwMCAhPSAxMi4uMTQnXG5cdFx0XHR9LFxuXHRcdFx0J3NzJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdzc3knOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3N0Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCdzdic6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J3N3Jzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQnc3lyJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd0YSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndGUnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3Rlbyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndGgnOiB7fSxcblx0XHRcdCd0aSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQndGlnJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd0ayc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndGwnOiB7XG5cdFx0XHRcdCdvbmUnOiAndiA9IDAgYW5kIGkgPSAxLDIsMyBvciB2ID0gMCBhbmQgaSAlIDEwICE9IDQsNiw5IG9yIHYgIT0gMCBhbmQgZiAlIDEwICE9IDQsNiw5J1xuXHRcdFx0fSxcblx0XHRcdCd0bic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndG8nOiB7fSxcblx0XHRcdCd0cic6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndHMnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3R6bSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSBvciBuID0gMTEuLjk5J1xuXHRcdFx0fSxcblx0XHRcdCd1Zyc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndWsnOiB7XG5cdFx0XHRcdCdvbmUnOiAndiA9IDAgYW5kIGkgJSAxMCA9IDEgYW5kIGkgJSAxMDAgIT0gMTEnLFxuXHRcdFx0XHQnZmV3JzogJ3YgPSAwIGFuZCBpICUgMTAgPSAyLi40IGFuZCBpICUgMTAwICE9IDEyLi4xNCcsXG5cdFx0XHRcdCdtYW55JzogJ3YgPSAwIGFuZCBpICUgMTAgPSAwIG9yIHYgPSAwIGFuZCBpICUgMTAgPSA1Li45IG9yIHYgPSAwIGFuZCBpICUgMTAwID0gMTEuLjE0J1xuXHRcdFx0fSxcblx0XHRcdCd1cic6IHtcblx0XHRcdFx0J29uZSc6ICdpID0gMSBhbmQgdiA9IDAnXG5cdFx0XHR9LFxuXHRcdFx0J3V6Jzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd2ZSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndmknOiB7fSxcblx0XHRcdCd2byc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMSdcblx0XHRcdH0sXG5cdFx0XHQndnVuJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd3YSc6IHtcblx0XHRcdFx0J29uZSc6ICduID0gMC4uMSdcblx0XHRcdH0sXG5cdFx0XHQnd2FlJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd3byc6IHt9LFxuXHRcdFx0J3hoJzoge1xuXHRcdFx0XHQnb25lJzogJ24gPSAxJ1xuXHRcdFx0fSxcblx0XHRcdCd4b2cnOiB7XG5cdFx0XHRcdCdvbmUnOiAnbiA9IDEnXG5cdFx0XHR9LFxuXHRcdFx0J3lpJzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAxIGFuZCB2ID0gMCdcblx0XHRcdH0sXG5cdFx0XHQneW8nOiB7fSxcblx0XHRcdCd5dWUnOiB7fSxcblx0XHRcdCd6aCc6IHt9LFxuXHRcdFx0J3p1Jzoge1xuXHRcdFx0XHQnb25lJzogJ2kgPSAwIG9yIG4gPSAxJ1xuXHRcdFx0fVxuXHRcdH0sXG5cdFx0Ly8ganNjczplbmFibGVcblxuXHRcdC8qKlxuXHRcdCAqIFBsdXJhbCBmb3JtIHRyYW5zZm9ybWF0aW9ucywgbmVlZGVkIGZvciBzb21lIGxhbmd1YWdlcy5cblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7aW50ZWdlcn0gY291bnRcblx0XHQgKiAgICAgICAgICAgIE5vbi1sb2NhbGl6ZWQgcXVhbnRpZmllclxuXHRcdCAqIEBwYXJhbSB7QXJyYXl9IGZvcm1zXG5cdFx0ICogICAgICAgICAgICBMaXN0IG9mIHBsdXJhbCBmb3Jtc1xuXHRcdCAqIEByZXR1cm4ge3N0cmluZ30gQ29ycmVjdCBmb3JtIGZvciBxdWFudGlmaWVyIGluIHRoaXMgbGFuZ3VhZ2Vcblx0XHQgKi9cblx0XHRjb252ZXJ0UGx1cmFsOiBmdW5jdGlvbiAoIGNvdW50LCBmb3JtcyApIHtcblx0XHRcdHZhciBwbHVyYWxSdWxlcyxcblx0XHRcdFx0cGx1cmFsRm9ybUluZGV4LFxuXHRcdFx0XHRpbmRleCxcblx0XHRcdFx0ZXhwbGljaXRQbHVyYWxQYXR0ZXJuID0gbmV3IFJlZ0V4cCggJ1xcXFxkKz0nLCAnaScgKSxcblx0XHRcdFx0Zm9ybUNvdW50LFxuXHRcdFx0XHRmb3JtO1xuXG5cdFx0XHRpZiAoICFmb3JtcyB8fCBmb3Jtcy5sZW5ndGggPT09IDAgKSB7XG5cdFx0XHRcdHJldHVybiAnJztcblx0XHRcdH1cblxuXHRcdFx0Ly8gSGFuZGxlIGZvciBFeHBsaWNpdCAwPSAmIDE9IHZhbHVlc1xuXHRcdFx0Zm9yICggaW5kZXggPSAwOyBpbmRleCA8IGZvcm1zLmxlbmd0aDsgaW5kZXgrKyApIHtcblx0XHRcdFx0Zm9ybSA9IGZvcm1zWyBpbmRleCBdO1xuXHRcdFx0XHRpZiAoIGV4cGxpY2l0UGx1cmFsUGF0dGVybi50ZXN0KCBmb3JtICkgKSB7XG5cdFx0XHRcdFx0Zm9ybUNvdW50ID0gcGFyc2VJbnQoIGZvcm0uc2xpY2UoIDAsIGZvcm0uaW5kZXhPZiggJz0nICkgKSwgMTAgKTtcblx0XHRcdFx0XHRpZiAoIGZvcm1Db3VudCA9PT0gY291bnQgKSB7XG5cdFx0XHRcdFx0XHRyZXR1cm4gKCBmb3JtLnNsaWNlKCBmb3JtLmluZGV4T2YoICc9JyApICsgMSApICk7XG5cdFx0XHRcdFx0fVxuXHRcdFx0XHRcdGZvcm1zWyBpbmRleCBdID0gdW5kZWZpbmVkO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cblx0XHRcdGZvcm1zID0gJC5tYXAoIGZvcm1zLCBmdW5jdGlvbiAoIGZvcm0gKSB7XG5cdFx0XHRcdGlmICggZm9ybSAhPT0gdW5kZWZpbmVkICkge1xuXHRcdFx0XHRcdHJldHVybiBmb3JtO1xuXHRcdFx0XHR9XG5cdFx0XHR9ICk7XG5cblx0XHRcdHBsdXJhbFJ1bGVzID0gdGhpcy5wbHVyYWxSdWxlc1sgJC5pMThuKCkubG9jYWxlIF07XG5cblx0XHRcdGlmICggIXBsdXJhbFJ1bGVzICkge1xuXHRcdFx0XHQvLyBkZWZhdWx0IGZhbGxiYWNrLlxuXHRcdFx0XHRyZXR1cm4gKCBjb3VudCA9PT0gMSApID8gZm9ybXNbIDAgXSA6IGZvcm1zWyAxIF07XG5cdFx0XHR9XG5cblx0XHRcdHBsdXJhbEZvcm1JbmRleCA9IHRoaXMuZ2V0UGx1cmFsRm9ybSggY291bnQsIHBsdXJhbFJ1bGVzICk7XG5cdFx0XHRwbHVyYWxGb3JtSW5kZXggPSBNYXRoLm1pbiggcGx1cmFsRm9ybUluZGV4LCBmb3Jtcy5sZW5ndGggLSAxICk7XG5cblx0XHRcdHJldHVybiBmb3Jtc1sgcGx1cmFsRm9ybUluZGV4IF07XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEZvciB0aGUgbnVtYmVyLCBnZXQgdGhlIHBsdXJhbCBmb3IgaW5kZXhcblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7aW50ZWdlcn0gbnVtYmVyXG5cdFx0ICogQHBhcmFtIHtPYmplY3R9IHBsdXJhbFJ1bGVzXG5cdFx0ICogQHJldHVybiB7aW50ZWdlcn0gcGx1cmFsIGZvcm0gaW5kZXhcblx0XHQgKi9cblx0XHRnZXRQbHVyYWxGb3JtOiBmdW5jdGlvbiAoIG51bWJlciwgcGx1cmFsUnVsZXMgKSB7XG5cdFx0XHR2YXIgaSxcblx0XHRcdFx0cGx1cmFsRm9ybXMgPSBbICd6ZXJvJywgJ29uZScsICd0d28nLCAnZmV3JywgJ21hbnknLCAnb3RoZXInIF0sXG5cdFx0XHRcdHBsdXJhbEZvcm1JbmRleCA9IDA7XG5cblx0XHRcdGZvciAoIGkgPSAwOyBpIDwgcGx1cmFsRm9ybXMubGVuZ3RoOyBpKysgKSB7XG5cdFx0XHRcdGlmICggcGx1cmFsUnVsZXNbIHBsdXJhbEZvcm1zWyBpIF0gXSApIHtcblx0XHRcdFx0XHRpZiAoIHBsdXJhbFJ1bGVQYXJzZXIoIHBsdXJhbFJ1bGVzWyBwbHVyYWxGb3Jtc1sgaSBdIF0sIG51bWJlciApICkge1xuXHRcdFx0XHRcdFx0cmV0dXJuIHBsdXJhbEZvcm1JbmRleDtcblx0XHRcdFx0XHR9XG5cblx0XHRcdFx0XHRwbHVyYWxGb3JtSW5kZXgrKztcblx0XHRcdFx0fVxuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gcGx1cmFsRm9ybUluZGV4O1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBDb252ZXJ0cyBhIG51bWJlciB1c2luZyBkaWdpdFRyYW5zZm9ybVRhYmxlLlxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtudW1iZXJ9IG51bSBWYWx1ZSB0byBiZSBjb252ZXJ0ZWRcblx0XHQgKiBAcGFyYW0ge2Jvb2xlYW59IGludGVnZXIgQ29udmVydCB0aGUgcmV0dXJuIHZhbHVlIHRvIGFuIGludGVnZXJcblx0XHQgKi9cblx0XHRjb252ZXJ0TnVtYmVyOiBmdW5jdGlvbiAoIG51bSwgaW50ZWdlciApIHtcblx0XHRcdHZhciB0bXAsIGl0ZW0sIGksXG5cdFx0XHRcdHRyYW5zZm9ybVRhYmxlLCBudW1iZXJTdHJpbmcsIGNvbnZlcnRlZE51bWJlcjtcblxuXHRcdFx0Ly8gU2V0IHRoZSB0YXJnZXQgVHJhbnNmb3JtIHRhYmxlOlxuXHRcdFx0dHJhbnNmb3JtVGFibGUgPSB0aGlzLmRpZ2l0VHJhbnNmb3JtVGFibGUoICQuaTE4bigpLmxvY2FsZSApO1xuXHRcdFx0bnVtYmVyU3RyaW5nID0gU3RyaW5nKCBudW0gKTtcblx0XHRcdGNvbnZlcnRlZE51bWJlciA9ICcnO1xuXG5cdFx0XHRpZiAoICF0cmFuc2Zvcm1UYWJsZSApIHtcblx0XHRcdFx0cmV0dXJuIG51bTtcblx0XHRcdH1cblxuXHRcdFx0Ly8gQ2hlY2sgaWYgdGhlIHJlc3RvcmUgdG8gTGF0aW4gbnVtYmVyIGZsYWcgaXMgc2V0OlxuXHRcdFx0aWYgKCBpbnRlZ2VyICkge1xuXHRcdFx0XHRpZiAoIHBhcnNlRmxvYXQoIG51bSwgMTAgKSA9PT0gbnVtICkge1xuXHRcdFx0XHRcdHJldHVybiBudW07XG5cdFx0XHRcdH1cblxuXHRcdFx0XHR0bXAgPSBbXTtcblxuXHRcdFx0XHRmb3IgKCBpdGVtIGluIHRyYW5zZm9ybVRhYmxlICkge1xuXHRcdFx0XHRcdHRtcFsgdHJhbnNmb3JtVGFibGVbIGl0ZW0gXSBdID0gaXRlbTtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdHRyYW5zZm9ybVRhYmxlID0gdG1wO1xuXHRcdFx0fVxuXG5cdFx0XHRmb3IgKCBpID0gMDsgaSA8IG51bWJlclN0cmluZy5sZW5ndGg7IGkrKyApIHtcblx0XHRcdFx0aWYgKCB0cmFuc2Zvcm1UYWJsZVsgbnVtYmVyU3RyaW5nWyBpIF0gXSApIHtcblx0XHRcdFx0XHRjb252ZXJ0ZWROdW1iZXIgKz0gdHJhbnNmb3JtVGFibGVbIG51bWJlclN0cmluZ1sgaSBdIF07XG5cdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0Y29udmVydGVkTnVtYmVyICs9IG51bWJlclN0cmluZ1sgaSBdO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cblx0XHRcdHJldHVybiBpbnRlZ2VyID8gcGFyc2VGbG9hdCggY29udmVydGVkTnVtYmVyLCAxMCApIDogY29udmVydGVkTnVtYmVyO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBHcmFtbWF0aWNhbCB0cmFuc2Zvcm1hdGlvbnMsIG5lZWRlZCBmb3IgaW5mbGVjdGVkIGxhbmd1YWdlcy5cblx0XHQgKiBJbnZva2VkIGJ5IHB1dHRpbmcge3tncmFtbWFyOmZvcm18d29yZH19IGluIGEgbWVzc2FnZS5cblx0XHQgKiBPdmVycmlkZSB0aGlzIG1ldGhvZCBmb3IgbGFuZ3VhZ2VzIHRoYXQgbmVlZCBzcGVjaWFsIGdyYW1tYXIgcnVsZXNcblx0XHQgKiBhcHBsaWVkIGR5bmFtaWNhbGx5LlxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd9IHdvcmRcblx0XHQgKiBAcGFyYW0ge3N0cmluZ30gZm9ybVxuXHRcdCAqIEByZXR1cm4ge3N0cmluZ31cblx0XHQgKi9cblx0XHRjb252ZXJ0R3JhbW1hcjogZnVuY3Rpb24gKCB3b3JkLCBmb3JtICkgeyAvKmpzaGludCB1bnVzZWQ6IGZhbHNlICovXG5cdFx0XHRyZXR1cm4gd29yZDtcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogUHJvdmlkZXMgYW4gYWx0ZXJuYXRpdmUgdGV4dCBkZXBlbmRpbmcgb24gc3BlY2lmaWVkIGdlbmRlci4gVXNhZ2Vcblx0XHQgKiB7e2dlbmRlcjpbZ2VuZGVyfHVzZXIgb2JqZWN0XXxtYXNjdWxpbmV8ZmVtaW5pbmV8bmV1dHJhbH19LiBJZiBzZWNvbmRcblx0XHQgKiBvciB0aGlyZCBwYXJhbWV0ZXIgYXJlIG5vdCBzcGVjaWZpZWQsIG1hc2N1bGluZSBpcyB1c2VkLlxuXHRcdCAqXG5cdFx0ICogVGhlc2UgZGV0YWlscyBtYXkgYmUgb3ZlcnJpZGVuIHBlciBsYW5ndWFnZS5cblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7c3RyaW5nfSBnZW5kZXJcblx0XHQgKiAgICAgIG1hbGUsIGZlbWFsZSwgb3IgYW55dGhpbmcgZWxzZSBmb3IgbmV1dHJhbC5cblx0XHQgKiBAcGFyYW0ge0FycmF5fSBmb3Jtc1xuXHRcdCAqICAgICAgTGlzdCBvZiBnZW5kZXIgZm9ybXNcblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge3N0cmluZ31cblx0XHQgKi9cblx0XHRnZW5kZXI6IGZ1bmN0aW9uICggZ2VuZGVyLCBmb3JtcyApIHtcblx0XHRcdGlmICggIWZvcm1zIHx8IGZvcm1zLmxlbmd0aCA9PT0gMCApIHtcblx0XHRcdFx0cmV0dXJuICcnO1xuXHRcdFx0fVxuXG5cdFx0XHR3aGlsZSAoIGZvcm1zLmxlbmd0aCA8IDIgKSB7XG5cdFx0XHRcdGZvcm1zLnB1c2goIGZvcm1zWyBmb3Jtcy5sZW5ndGggLSAxIF0gKTtcblx0XHRcdH1cblxuXHRcdFx0aWYgKCBnZW5kZXIgPT09ICdtYWxlJyApIHtcblx0XHRcdFx0cmV0dXJuIGZvcm1zWyAwIF07XG5cdFx0XHR9XG5cblx0XHRcdGlmICggZ2VuZGVyID09PSAnZmVtYWxlJyApIHtcblx0XHRcdFx0cmV0dXJuIGZvcm1zWyAxIF07XG5cdFx0XHR9XG5cblx0XHRcdHJldHVybiAoIGZvcm1zLmxlbmd0aCA9PT0gMyApID8gZm9ybXNbIDIgXSA6IGZvcm1zWyAwIF07XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEdldCB0aGUgZGlnaXQgdHJhbnNmb3JtIHRhYmxlIGZvciB0aGUgZ2l2ZW4gbGFuZ3VhZ2Vcblx0XHQgKiBTZWUgaHR0cDovL2NsZHIudW5pY29kZS5vcmcvdHJhbnNsYXRpb24vbnVtYmVyaW5nLXN5c3RlbXNcblx0XHQgKlxuXHRcdCAqIEBwYXJhbSB7c3RyaW5nfSBsYW5ndWFnZVxuXHRcdCAqIEByZXR1cm4ge0FycmF5fGJvb2xlYW59IExpc3Qgb2YgZGlnaXRzIGluIHRoZSBwYXNzZWQgbGFuZ3VhZ2Ugb3IgZmFsc2Vcblx0XHQgKiByZXByZXNlbnRhdGlvbiwgb3IgYm9vbGVhbiBmYWxzZSBpZiB0aGVyZSBpcyBubyBpbmZvcm1hdGlvbi5cblx0XHQgKi9cblx0XHRkaWdpdFRyYW5zZm9ybVRhYmxlOiBmdW5jdGlvbiAoIGxhbmd1YWdlICkge1xuXHRcdFx0dmFyIHRhYmxlcyA9IHtcblx0XHRcdFx0YXI6ICfZoNmh2aLZo9mk2aXZptmn2ajZqScsXG5cdFx0XHRcdGZhOiAn27Dbsduy27PbtNu127bbt9u427knLFxuXHRcdFx0XHRtbDogJ+C1puC1p+C1qOC1qeC1quC1q+C1rOC1reC1ruC1rycsXG5cdFx0XHRcdGtuOiAn4LOm4LOn4LOo4LOp4LOq4LOr4LOs4LOt4LOu4LOvJyxcblx0XHRcdFx0bG86ICfgu5Dgu5Hgu5Lgu5Pgu5Tgu5Xgu5bgu5fgu5jgu5knLFxuXHRcdFx0XHRvcjogJ+CtpuCtp+CtqOCtqeCtquCtq+CtrOCtreCtruCtrycsXG5cdFx0XHRcdGtoOiAn4Z+g4Z+h4Z+i4Z+j4Z+k4Z+l4Z+m4Z+n4Z+o4Z+pJyxcblx0XHRcdFx0cGE6ICfgqabgqafgqajgqangqargqavgqazgqa3gqa7gqa8nLFxuXHRcdFx0XHRndTogJ+CrpuCrp+CrqOCrqeCrquCrq+CrrOCrreCrruCrrycsXG5cdFx0XHRcdGhpOiAn4KWm4KWn4KWo4KWp4KWq4KWr4KWs4KWt4KWu4KWvJyxcblx0XHRcdFx0bXk6ICfhgYDhgYHhgYLhgYPhgYThgYXhgYbhgYfhgYjhgYknLFxuXHRcdFx0XHR0YTogJ+CvpuCvp+CvqOCvqeCvquCvq+CvrOCvreCvruCvrycsXG5cdFx0XHRcdHRlOiAn4LGm4LGn4LGo4LGp4LGq4LGr4LGs4LGt4LGu4LGvJyxcblx0XHRcdFx0dGg6ICfguZDguZHguZLguZPguZTguZXguZbguZfguZjguZknLCAvLyBGSVhNRSB1c2UgaXNvIDYzOSBjb2Rlc1xuXHRcdFx0XHRibzogJ+C8oOC8oeC8ouC8o+C8pOC8peC8puC8p+C8qOC8qScgLy8gRklYTUUgdXNlIGlzbyA2MzkgY29kZXNcblx0XHRcdH07XG5cblx0XHRcdGlmICggIXRhYmxlc1sgbGFuZ3VhZ2UgXSApIHtcblx0XHRcdFx0cmV0dXJuIGZhbHNlO1xuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gdGFibGVzWyBsYW5ndWFnZSBdLnNwbGl0KCAnJyApO1xuXHRcdH1cblx0fTtcblxuXHQkLmV4dGVuZCggJC5pMThuLmxhbmd1YWdlcywge1xuXHRcdCdkZWZhdWx0JzogbGFuZ3VhZ2Vcblx0fSApO1xufSggalF1ZXJ5ICkgKTtcbi8qKlxuICogY2xkcnBsdXJhbHBhcnNlci5qc1xuICogQSBwYXJzZXIgZW5naW5lIGZvciBDTERSIHBsdXJhbCBydWxlcy5cbiAqXG4gKiBDb3B5cmlnaHQgMjAxMi0yMDE0IFNhbnRob3NoIFRob3R0aW5nYWwgYW5kIG90aGVyIGNvbnRyaWJ1dG9yc1xuICogUmVsZWFzZWQgdW5kZXIgdGhlIE1JVCBsaWNlbnNlXG4gKiBodHRwOi8vb3BlbnNvdXJjZS5vcmcvbGljZW5zZXMvTUlUXG4gKlxuICogQHZlcnNpb24gMC4xLjBcbiAqIEBzb3VyY2UgaHR0cHM6Ly9naXRodWIuY29tL3NhbnRob3NodHIvQ0xEUlBsdXJhbFJ1bGVQYXJzZXJcbiAqIEBhdXRob3IgU2FudGhvc2ggVGhvdHRpbmdhbCA8c2FudGhvc2gudGhvdHRpbmdhbEBnbWFpbC5jb20+XG4gKiBAYXV0aG9yIFRpbW8gVGlqaG9mXG4gKiBAYXV0aG9yIEFtaXIgQWhhcm9uaVxuICovXG5cbi8qKlxuICogRXZhbHVhdGVzIGEgcGx1cmFsIHJ1bGUgaW4gQ0xEUiBzeW50YXggZm9yIGEgbnVtYmVyXG4gKiBAcGFyYW0ge3N0cmluZ30gcnVsZVxuICogQHBhcmFtIHtpbnRlZ2VyfSBudW1iZXJcbiAqIEByZXR1cm4ge2Jvb2xlYW59IHRydWUgaWYgZXZhbHVhdGlvbiBwYXNzZWQsIGZhbHNlIGlmIGV2YWx1YXRpb24gZmFpbGVkLlxuICovXG5cbi8vIFVNRCByZXR1cm5FeHBvcnRzIGh0dHBzOi8vZ2l0aHViLmNvbS91bWRqcy91bWQvYmxvYi9tYXN0ZXIvcmV0dXJuRXhwb3J0cy5qc1xuKGZ1bmN0aW9uKHJvb3QsIGZhY3RvcnkpIHtcblx0aWYgKHR5cGVvZiBkZWZpbmUgPT09ICdmdW5jdGlvbicgJiYgZGVmaW5lLmFtZCkge1xuXHRcdC8vIEFNRC4gUmVnaXN0ZXIgYXMgYW4gYW5vbnltb3VzIG1vZHVsZS5cblx0XHRkZWZpbmUoZmFjdG9yeSk7XG5cdH0gZWxzZSBpZiAodHlwZW9mIGV4cG9ydHMgPT09ICdvYmplY3QnKSB7XG5cdFx0Ly8gTm9kZS4gRG9lcyBub3Qgd29yayB3aXRoIHN0cmljdCBDb21tb25KUywgYnV0XG5cdFx0Ly8gb25seSBDb21tb25KUy1saWtlIGVudmlyb25tZW50cyB0aGF0IHN1cHBvcnQgbW9kdWxlLmV4cG9ydHMsXG5cdFx0Ly8gbGlrZSBOb2RlLlxuXHRcdG1vZHVsZS5leHBvcnRzID0gZmFjdG9yeSgpO1xuXHR9IGVsc2Uge1xuXHRcdC8vIEJyb3dzZXIgZ2xvYmFscyAocm9vdCBpcyB3aW5kb3cpXG5cdFx0cm9vdC5wbHVyYWxSdWxlUGFyc2VyID0gZmFjdG9yeSgpO1xuXHR9XG59KHRoaXMsIGZ1bmN0aW9uKCkge1xuXG53aW5kb3cucGx1cmFsUnVsZVBhcnNlciA9IGZ1bmN0aW9uKHJ1bGUsIG51bWJlcikge1xuXHQndXNlIHN0cmljdCc7XG5cblx0Lypcblx0U3ludGF4OiBzZWUgaHR0cDovL3VuaWNvZGUub3JnL3JlcG9ydHMvdHIzNS8jTGFuZ3VhZ2VfUGx1cmFsX1J1bGVzXG5cdC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tXG5cdGNvbmRpdGlvbiAgICAgPSBhbmRfY29uZGl0aW9uICgnb3InIGFuZF9jb25kaXRpb24pKlxuXHRcdCgnQGludGVnZXInIHNhbXBsZXMpP1xuXHRcdCgnQGRlY2ltYWwnIHNhbXBsZXMpP1xuXHRhbmRfY29uZGl0aW9uID0gcmVsYXRpb24gKCdhbmQnIHJlbGF0aW9uKSpcblx0cmVsYXRpb24gICAgICA9IGlzX3JlbGF0aW9uIHwgaW5fcmVsYXRpb24gfCB3aXRoaW5fcmVsYXRpb25cblx0aXNfcmVsYXRpb24gICA9IGV4cHIgJ2lzJyAoJ25vdCcpPyB2YWx1ZVxuXHRpbl9yZWxhdGlvbiAgID0gZXhwciAoKCdub3QnKT8gJ2luJyB8ICc9JyB8ICchPScpIHJhbmdlX2xpc3Rcblx0d2l0aGluX3JlbGF0aW9uID0gZXhwciAoJ25vdCcpPyAnd2l0aGluJyByYW5nZV9saXN0XG5cdGV4cHIgICAgICAgICAgPSBvcGVyYW5kICgoJ21vZCcgfCAnJScpIHZhbHVlKT9cblx0b3BlcmFuZCAgICAgICA9ICduJyB8ICdpJyB8ICdmJyB8ICd0JyB8ICd2JyB8ICd3J1xuXHRyYW5nZV9saXN0ICAgID0gKHJhbmdlIHwgdmFsdWUpICgnLCcgcmFuZ2VfbGlzdCkqXG5cdHZhbHVlICAgICAgICAgPSBkaWdpdCtcblx0ZGlnaXQgICAgICAgICA9IDB8MXwyfDN8NHw1fDZ8N3w4fDlcblx0cmFuZ2UgICAgICAgICA9IHZhbHVlJy4uJ3ZhbHVlXG5cdHNhbXBsZXMgICAgICAgPSBzYW1wbGVSYW5nZSAoJywnIHNhbXBsZVJhbmdlKSogKCcsJyAoJ+KApid8Jy4uLicpKT9cblx0c2FtcGxlUmFuZ2UgICA9IGRlY2ltYWxWYWx1ZSAnficgZGVjaW1hbFZhbHVlXG5cdGRlY2ltYWxWYWx1ZSAgPSB2YWx1ZSAoJy4nIHZhbHVlKT9cblx0Ki9cblxuXHQvLyBXZSBkb24ndCBldmFsdWF0ZSB0aGUgc2FtcGxlcyBzZWN0aW9uIG9mIHRoZSBydWxlLiBJZ25vcmUgaXQuXG5cdHJ1bGUgPSBydWxlLnNwbGl0KCdAJylbMF0ucmVwbGFjZSgvXlxccyovLCAnJykucmVwbGFjZSgvXFxzKiQvLCAnJyk7XG5cblx0aWYgKCFydWxlLmxlbmd0aCkge1xuXHRcdC8vIEVtcHR5IHJ1bGUgb3IgJ290aGVyJyBydWxlLlxuXHRcdHJldHVybiB0cnVlO1xuXHR9XG5cblx0Ly8gSW5kaWNhdGVzIHRoZSBjdXJyZW50IHBvc2l0aW9uIGluIHRoZSBydWxlIGFzIHdlIHBhcnNlIHRocm91Z2ggaXQuXG5cdC8vIFNoYXJlZCBhbW9uZyBhbGwgcGFyc2luZyBmdW5jdGlvbnMgYmVsb3cuXG5cdHZhciBwb3MgPSAwLFxuXHRcdG9wZXJhbmQsXG5cdFx0ZXhwcmVzc2lvbixcblx0XHRyZWxhdGlvbixcblx0XHRyZXN1bHQsXG5cdFx0d2hpdGVzcGFjZSA9IG1ha2VSZWdleFBhcnNlcigvXlxccysvKSxcblx0XHR2YWx1ZSA9IG1ha2VSZWdleFBhcnNlcigvXlxcZCsvKSxcblx0XHRfbl8gPSBtYWtlU3RyaW5nUGFyc2VyKCduJyksXG5cdFx0X2lfID0gbWFrZVN0cmluZ1BhcnNlcignaScpLFxuXHRcdF9mXyA9IG1ha2VTdHJpbmdQYXJzZXIoJ2YnKSxcblx0XHRfdF8gPSBtYWtlU3RyaW5nUGFyc2VyKCd0JyksXG5cdFx0X3ZfID0gbWFrZVN0cmluZ1BhcnNlcigndicpLFxuXHRcdF93XyA9IG1ha2VTdHJpbmdQYXJzZXIoJ3cnKSxcblx0XHRfaXNfID0gbWFrZVN0cmluZ1BhcnNlcignaXMnKSxcblx0XHRfaXNub3RfID0gbWFrZVN0cmluZ1BhcnNlcignaXMgbm90JyksXG5cdFx0X2lzbm90X3NpZ25fID0gbWFrZVN0cmluZ1BhcnNlcignIT0nKSxcblx0XHRfZXF1YWxfID0gbWFrZVN0cmluZ1BhcnNlcignPScpLFxuXHRcdF9tb2RfID0gbWFrZVN0cmluZ1BhcnNlcignbW9kJyksXG5cdFx0X3BlcmNlbnRfID0gbWFrZVN0cmluZ1BhcnNlcignJScpLFxuXHRcdF9ub3RfID0gbWFrZVN0cmluZ1BhcnNlcignbm90JyksXG5cdFx0X2luXyA9IG1ha2VTdHJpbmdQYXJzZXIoJ2luJyksXG5cdFx0X3dpdGhpbl8gPSBtYWtlU3RyaW5nUGFyc2VyKCd3aXRoaW4nKSxcblx0XHRfcmFuZ2VfID0gbWFrZVN0cmluZ1BhcnNlcignLi4nKSxcblx0XHRfY29tbWFfID0gbWFrZVN0cmluZ1BhcnNlcignLCcpLFxuXHRcdF9vcl8gPSBtYWtlU3RyaW5nUGFyc2VyKCdvcicpLFxuXHRcdF9hbmRfID0gbWFrZVN0cmluZ1BhcnNlcignYW5kJyk7XG5cblx0ZnVuY3Rpb24gZGVidWcoKSB7XG5cdFx0Ly8gY29uc29sZS5sb2cuYXBwbHkoY29uc29sZSwgYXJndW1lbnRzKTtcblx0fVxuXG5cdGRlYnVnKCdwbHVyYWxSdWxlUGFyc2VyJywgcnVsZSwgbnVtYmVyKTtcblxuXHQvLyBUcnkgcGFyc2VycyB1bnRpbCBvbmUgd29ya3MsIGlmIG5vbmUgd29yayByZXR1cm4gbnVsbFxuXHRmdW5jdGlvbiBjaG9pY2UocGFyc2VyU3ludGF4KSB7XG5cdFx0cmV0dXJuIGZ1bmN0aW9uKCkge1xuXHRcdFx0dmFyIGksIHJlc3VsdDtcblxuXHRcdFx0Zm9yIChpID0gMDsgaSA8IHBhcnNlclN5bnRheC5sZW5ndGg7IGkrKykge1xuXHRcdFx0XHRyZXN1bHQgPSBwYXJzZXJTeW50YXhbaV0oKTtcblxuXHRcdFx0XHRpZiAocmVzdWx0ICE9PSBudWxsKSB7XG5cdFx0XHRcdFx0cmV0dXJuIHJlc3VsdDtcblx0XHRcdFx0fVxuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gbnVsbDtcblx0XHR9O1xuXHR9XG5cblx0Ly8gVHJ5IHNldmVyYWwgcGFyc2VyU3ludGF4LWVzIGluIGEgcm93LlxuXHQvLyBBbGwgbXVzdCBzdWNjZWVkOyBvdGhlcndpc2UsIHJldHVybiBudWxsLlxuXHQvLyBUaGlzIGlzIHRoZSBvbmx5IGVhZ2VyIG9uZS5cblx0ZnVuY3Rpb24gc2VxdWVuY2UocGFyc2VyU3ludGF4KSB7XG5cdFx0dmFyIGksIHBhcnNlclJlcyxcblx0XHRcdG9yaWdpbmFsUG9zID0gcG9zLFxuXHRcdFx0cmVzdWx0ID0gW107XG5cblx0XHRmb3IgKGkgPSAwOyBpIDwgcGFyc2VyU3ludGF4Lmxlbmd0aDsgaSsrKSB7XG5cdFx0XHRwYXJzZXJSZXMgPSBwYXJzZXJTeW50YXhbaV0oKTtcblxuXHRcdFx0aWYgKHBhcnNlclJlcyA9PT0gbnVsbCkge1xuXHRcdFx0XHRwb3MgPSBvcmlnaW5hbFBvcztcblxuXHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdH1cblxuXHRcdFx0cmVzdWx0LnB1c2gocGFyc2VyUmVzKTtcblx0XHR9XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0Ly8gUnVuIHRoZSBzYW1lIHBhcnNlciBvdmVyIGFuZCBvdmVyIHVudGlsIGl0IGZhaWxzLlxuXHQvLyBNdXN0IHN1Y2NlZWQgYSBtaW5pbXVtIG9mIG4gdGltZXM7IG90aGVyd2lzZSwgcmV0dXJuIG51bGwuXG5cdGZ1bmN0aW9uIG5Pck1vcmUobiwgcCkge1xuXHRcdHJldHVybiBmdW5jdGlvbigpIHtcblx0XHRcdHZhciBvcmlnaW5hbFBvcyA9IHBvcyxcblx0XHRcdFx0cmVzdWx0ID0gW10sXG5cdFx0XHRcdHBhcnNlZCA9IHAoKTtcblxuXHRcdFx0d2hpbGUgKHBhcnNlZCAhPT0gbnVsbCkge1xuXHRcdFx0XHRyZXN1bHQucHVzaChwYXJzZWQpO1xuXHRcdFx0XHRwYXJzZWQgPSBwKCk7XG5cdFx0XHR9XG5cblx0XHRcdGlmIChyZXN1bHQubGVuZ3RoIDwgbikge1xuXHRcdFx0XHRwb3MgPSBvcmlnaW5hbFBvcztcblxuXHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIHJlc3VsdDtcblx0XHR9O1xuXHR9XG5cblx0Ly8gSGVscGVycyAtIGp1c3QgbWFrZSBwYXJzZXJTeW50YXggb3V0IG9mIHNpbXBsZXIgSlMgYnVpbHRpbiB0eXBlc1xuXHRmdW5jdGlvbiBtYWtlU3RyaW5nUGFyc2VyKHMpIHtcblx0XHR2YXIgbGVuID0gcy5sZW5ndGg7XG5cblx0XHRyZXR1cm4gZnVuY3Rpb24oKSB7XG5cdFx0XHR2YXIgcmVzdWx0ID0gbnVsbDtcblxuXHRcdFx0aWYgKHJ1bGUuc3Vic3RyKHBvcywgbGVuKSA9PT0gcykge1xuXHRcdFx0XHRyZXN1bHQgPSBzO1xuXHRcdFx0XHRwb3MgKz0gbGVuO1xuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdH07XG5cdH1cblxuXHRmdW5jdGlvbiBtYWtlUmVnZXhQYXJzZXIocmVnZXgpIHtcblx0XHRyZXR1cm4gZnVuY3Rpb24oKSB7XG5cdFx0XHR2YXIgbWF0Y2hlcyA9IHJ1bGUuc3Vic3RyKHBvcykubWF0Y2gocmVnZXgpO1xuXG5cdFx0XHRpZiAobWF0Y2hlcyA9PT0gbnVsbCkge1xuXHRcdFx0XHRyZXR1cm4gbnVsbDtcblx0XHRcdH1cblxuXHRcdFx0cG9zICs9IG1hdGNoZXNbMF0ubGVuZ3RoO1xuXG5cdFx0XHRyZXR1cm4gbWF0Y2hlc1swXTtcblx0XHR9O1xuXHR9XG5cblx0LyoqXG5cdCAqIEludGVnZXIgZGlnaXRzIG9mIG4uXG5cdCAqL1xuXHRmdW5jdGlvbiBpKCkge1xuXHRcdHZhciByZXN1bHQgPSBfaV8oKTtcblxuXHRcdGlmIChyZXN1bHQgPT09IG51bGwpIHtcblx0XHRcdGRlYnVnKCcgLS0gZmFpbGVkIGknLCBwYXJzZUludChudW1iZXIsIDEwKSk7XG5cblx0XHRcdHJldHVybiByZXN1bHQ7XG5cdFx0fVxuXG5cdFx0cmVzdWx0ID0gcGFyc2VJbnQobnVtYmVyLCAxMCk7XG5cdFx0ZGVidWcoJyAtLSBwYXNzZWQgaSAnLCByZXN1bHQpO1xuXG5cdFx0cmV0dXJuIHJlc3VsdDtcblx0fVxuXG5cdC8qKlxuXHQgKiBBYnNvbHV0ZSB2YWx1ZSBvZiB0aGUgc291cmNlIG51bWJlciAoaW50ZWdlciBhbmQgZGVjaW1hbHMpLlxuXHQgKi9cblx0ZnVuY3Rpb24gbigpIHtcblx0XHR2YXIgcmVzdWx0ID0gX25fKCk7XG5cblx0XHRpZiAocmVzdWx0ID09PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIGZhaWxlZCBuICcsIG51bWJlcik7XG5cblx0XHRcdHJldHVybiByZXN1bHQ7XG5cdFx0fVxuXG5cdFx0cmVzdWx0ID0gcGFyc2VGbG9hdChudW1iZXIsIDEwKTtcblx0XHRkZWJ1ZygnIC0tIHBhc3NlZCBuICcsIHJlc3VsdCk7XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0LyoqXG5cdCAqIFZpc2libGUgZnJhY3Rpb25hbCBkaWdpdHMgaW4gbiwgd2l0aCB0cmFpbGluZyB6ZXJvcy5cblx0ICovXG5cdGZ1bmN0aW9uIGYoKSB7XG5cdFx0dmFyIHJlc3VsdCA9IF9mXygpO1xuXG5cdFx0aWYgKHJlc3VsdCA9PT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBmYWlsZWQgZiAnLCBudW1iZXIpO1xuXG5cdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdH1cblxuXHRcdHJlc3VsdCA9IChudW1iZXIgKyAnLicpLnNwbGl0KCcuJylbMV0gfHwgMDtcblx0XHRkZWJ1ZygnIC0tIHBhc3NlZCBmICcsIHJlc3VsdCk7XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0LyoqXG5cdCAqIFZpc2libGUgZnJhY3Rpb25hbCBkaWdpdHMgaW4gbiwgd2l0aG91dCB0cmFpbGluZyB6ZXJvcy5cblx0ICovXG5cdGZ1bmN0aW9uIHQoKSB7XG5cdFx0dmFyIHJlc3VsdCA9IF90XygpO1xuXG5cdFx0aWYgKHJlc3VsdCA9PT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBmYWlsZWQgdCAnLCBudW1iZXIpO1xuXG5cdFx0XHRyZXR1cm4gcmVzdWx0O1xuXHRcdH1cblxuXHRcdHJlc3VsdCA9IChudW1iZXIgKyAnLicpLnNwbGl0KCcuJylbMV0ucmVwbGFjZSgvMCQvLCAnJykgfHwgMDtcblx0XHRkZWJ1ZygnIC0tIHBhc3NlZCB0ICcsIHJlc3VsdCk7XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0LyoqXG5cdCAqIE51bWJlciBvZiB2aXNpYmxlIGZyYWN0aW9uIGRpZ2l0cyBpbiBuLCB3aXRoIHRyYWlsaW5nIHplcm9zLlxuXHQgKi9cblx0ZnVuY3Rpb24gdigpIHtcblx0XHR2YXIgcmVzdWx0ID0gX3ZfKCk7XG5cblx0XHRpZiAocmVzdWx0ID09PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIGZhaWxlZCB2ICcsIG51bWJlcik7XG5cblx0XHRcdHJldHVybiByZXN1bHQ7XG5cdFx0fVxuXG5cdFx0cmVzdWx0ID0gKG51bWJlciArICcuJykuc3BsaXQoJy4nKVsxXS5sZW5ndGggfHwgMDtcblx0XHRkZWJ1ZygnIC0tIHBhc3NlZCB2ICcsIHJlc3VsdCk7XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0LyoqXG5cdCAqIE51bWJlciBvZiB2aXNpYmxlIGZyYWN0aW9uIGRpZ2l0cyBpbiBuLCB3aXRob3V0IHRyYWlsaW5nIHplcm9zLlxuXHQgKi9cblx0ZnVuY3Rpb24gdygpIHtcblx0XHR2YXIgcmVzdWx0ID0gX3dfKCk7XG5cblx0XHRpZiAocmVzdWx0ID09PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIGZhaWxlZCB3ICcsIG51bWJlcik7XG5cblx0XHRcdHJldHVybiByZXN1bHQ7XG5cdFx0fVxuXG5cdFx0cmVzdWx0ID0gKG51bWJlciArICcuJykuc3BsaXQoJy4nKVsxXS5yZXBsYWNlKC8wJC8sICcnKS5sZW5ndGggfHwgMDtcblx0XHRkZWJ1ZygnIC0tIHBhc3NlZCB3ICcsIHJlc3VsdCk7XG5cblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9XG5cblx0Ly8gb3BlcmFuZCAgICAgICA9ICduJyB8ICdpJyB8ICdmJyB8ICd0JyB8ICd2JyB8ICd3J1xuXHRvcGVyYW5kID0gY2hvaWNlKFtuLCBpLCBmLCB0LCB2LCB3XSk7XG5cblx0Ly8gZXhwciAgICAgICAgICA9IG9wZXJhbmQgKCgnbW9kJyB8ICclJykgdmFsdWUpP1xuXHRleHByZXNzaW9uID0gY2hvaWNlKFttb2QsIG9wZXJhbmRdKTtcblxuXHRmdW5jdGlvbiBtb2QoKSB7XG5cdFx0dmFyIHJlc3VsdCA9IHNlcXVlbmNlKFxuXHRcdFx0W29wZXJhbmQsIHdoaXRlc3BhY2UsIGNob2ljZShbX21vZF8sIF9wZXJjZW50X10pLCB3aGl0ZXNwYWNlLCB2YWx1ZV1cblx0XHQpO1xuXG5cdFx0aWYgKHJlc3VsdCA9PT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBmYWlsZWQgbW9kJyk7XG5cblx0XHRcdHJldHVybiBudWxsO1xuXHRcdH1cblxuXHRcdGRlYnVnKCcgLS0gcGFzc2VkICcgKyBwYXJzZUludChyZXN1bHRbMF0sIDEwKSArICcgJyArIHJlc3VsdFsyXSArICcgJyArIHBhcnNlSW50KHJlc3VsdFs0XSwgMTApKTtcblxuXHRcdHJldHVybiBwYXJzZUludChyZXN1bHRbMF0sIDEwKSAlIHBhcnNlSW50KHJlc3VsdFs0XSwgMTApO1xuXHR9XG5cblx0ZnVuY3Rpb24gbm90KCkge1xuXHRcdHZhciByZXN1bHQgPSBzZXF1ZW5jZShbd2hpdGVzcGFjZSwgX25vdF9dKTtcblxuXHRcdGlmIChyZXN1bHQgPT09IG51bGwpIHtcblx0XHRcdGRlYnVnKCcgLS0gZmFpbGVkIG5vdCcpO1xuXG5cdFx0XHRyZXR1cm4gbnVsbDtcblx0XHR9XG5cblx0XHRyZXR1cm4gcmVzdWx0WzFdO1xuXHR9XG5cblx0Ly8gaXNfcmVsYXRpb24gICA9IGV4cHIgJ2lzJyAoJ25vdCcpPyB2YWx1ZVxuXHRmdW5jdGlvbiBpcygpIHtcblx0XHR2YXIgcmVzdWx0ID0gc2VxdWVuY2UoW2V4cHJlc3Npb24sIHdoaXRlc3BhY2UsIGNob2ljZShbX2lzX10pLCB3aGl0ZXNwYWNlLCB2YWx1ZV0pO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBwYXNzZWQgaXMgOiAnICsgcmVzdWx0WzBdICsgJyA9PSAnICsgcGFyc2VJbnQocmVzdWx0WzRdLCAxMCkpO1xuXG5cdFx0XHRyZXR1cm4gcmVzdWx0WzBdID09PSBwYXJzZUludChyZXN1bHRbNF0sIDEwKTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCBpcycpO1xuXG5cdFx0cmV0dXJuIG51bGw7XG5cdH1cblxuXHQvLyBpc19yZWxhdGlvbiAgID0gZXhwciAnaXMnICgnbm90Jyk/IHZhbHVlXG5cdGZ1bmN0aW9uIGlzbm90KCkge1xuXHRcdHZhciByZXN1bHQgPSBzZXF1ZW5jZShcblx0XHRcdFtleHByZXNzaW9uLCB3aGl0ZXNwYWNlLCBjaG9pY2UoW19pc25vdF8sIF9pc25vdF9zaWduX10pLCB3aGl0ZXNwYWNlLCB2YWx1ZV1cblx0XHQpO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBwYXNzZWQgaXNub3Q6ICcgKyByZXN1bHRbMF0gKyAnICE9ICcgKyBwYXJzZUludChyZXN1bHRbNF0sIDEwKSk7XG5cblx0XHRcdHJldHVybiByZXN1bHRbMF0gIT09IHBhcnNlSW50KHJlc3VsdFs0XSwgMTApO1xuXHRcdH1cblxuXHRcdGRlYnVnKCcgLS0gZmFpbGVkIGlzbm90Jyk7XG5cblx0XHRyZXR1cm4gbnVsbDtcblx0fVxuXG5cdGZ1bmN0aW9uIG5vdF9pbigpIHtcblx0XHR2YXIgaSwgcmFuZ2VfbGlzdCxcblx0XHRcdHJlc3VsdCA9IHNlcXVlbmNlKFtleHByZXNzaW9uLCB3aGl0ZXNwYWNlLCBfaXNub3Rfc2lnbl8sIHdoaXRlc3BhY2UsIHJhbmdlTGlzdF0pO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBwYXNzZWQgbm90X2luOiAnICsgcmVzdWx0WzBdICsgJyAhPSAnICsgcmVzdWx0WzRdKTtcblx0XHRcdHJhbmdlX2xpc3QgPSByZXN1bHRbNF07XG5cblx0XHRcdGZvciAoaSA9IDA7IGkgPCByYW5nZV9saXN0Lmxlbmd0aDsgaSsrKSB7XG5cdFx0XHRcdGlmIChwYXJzZUludChyYW5nZV9saXN0W2ldLCAxMCkgPT09IHBhcnNlSW50KHJlc3VsdFswXSwgMTApKSB7XG5cdFx0XHRcdFx0cmV0dXJuIGZhbHNlO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cblx0XHRcdHJldHVybiB0cnVlO1xuXHRcdH1cblxuXHRcdGRlYnVnKCcgLS0gZmFpbGVkIG5vdF9pbicpO1xuXG5cdFx0cmV0dXJuIG51bGw7XG5cdH1cblxuXHQvLyByYW5nZV9saXN0ICAgID0gKHJhbmdlIHwgdmFsdWUpICgnLCcgcmFuZ2VfbGlzdCkqXG5cdGZ1bmN0aW9uIHJhbmdlTGlzdCgpIHtcblx0XHR2YXIgcmVzdWx0ID0gc2VxdWVuY2UoW2Nob2ljZShbcmFuZ2UsIHZhbHVlXSksIG5Pck1vcmUoMCwgcmFuZ2VUYWlsKV0pLFxuXHRcdFx0cmVzdWx0TGlzdCA9IFtdO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0cmVzdWx0TGlzdCA9IHJlc3VsdExpc3QuY29uY2F0KHJlc3VsdFswXSk7XG5cblx0XHRcdGlmIChyZXN1bHRbMV1bMF0pIHtcblx0XHRcdFx0cmVzdWx0TGlzdCA9IHJlc3VsdExpc3QuY29uY2F0KHJlc3VsdFsxXVswXSk7XG5cdFx0XHR9XG5cblx0XHRcdHJldHVybiByZXN1bHRMaXN0O1xuXHRcdH1cblxuXHRcdGRlYnVnKCcgLS0gZmFpbGVkIHJhbmdlTGlzdCcpO1xuXG5cdFx0cmV0dXJuIG51bGw7XG5cdH1cblxuXHRmdW5jdGlvbiByYW5nZVRhaWwoKSB7XG5cdFx0Ly8gJywnIHJhbmdlX2xpc3Rcblx0XHR2YXIgcmVzdWx0ID0gc2VxdWVuY2UoW19jb21tYV8sIHJhbmdlTGlzdF0pO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0cmV0dXJuIHJlc3VsdFsxXTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCByYW5nZVRhaWwnKTtcblxuXHRcdHJldHVybiBudWxsO1xuXHR9XG5cblx0Ly8gcmFuZ2UgICAgICAgICA9IHZhbHVlJy4uJ3ZhbHVlXG5cdGZ1bmN0aW9uIHJhbmdlKCkge1xuXHRcdHZhciBpLCBhcnJheSwgbGVmdCwgcmlnaHQsXG5cdFx0XHRyZXN1bHQgPSBzZXF1ZW5jZShbdmFsdWUsIF9yYW5nZV8sIHZhbHVlXSk7XG5cblx0XHRpZiAocmVzdWx0ICE9PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIHBhc3NlZCByYW5nZScpO1xuXG5cdFx0XHRhcnJheSA9IFtdO1xuXHRcdFx0bGVmdCA9IHBhcnNlSW50KHJlc3VsdFswXSwgMTApO1xuXHRcdFx0cmlnaHQgPSBwYXJzZUludChyZXN1bHRbMl0sIDEwKTtcblxuXHRcdFx0Zm9yIChpID0gbGVmdDsgaSA8PSByaWdodDsgaSsrKSB7XG5cdFx0XHRcdGFycmF5LnB1c2goaSk7XG5cdFx0XHR9XG5cblx0XHRcdHJldHVybiBhcnJheTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCByYW5nZScpO1xuXG5cdFx0cmV0dXJuIG51bGw7XG5cdH1cblxuXHRmdW5jdGlvbiBfaW4oKSB7XG5cdFx0dmFyIHJlc3VsdCwgcmFuZ2VfbGlzdCwgaTtcblxuXHRcdC8vIGluX3JlbGF0aW9uICAgPSBleHByICgnbm90Jyk/ICdpbicgcmFuZ2VfbGlzdFxuXHRcdHJlc3VsdCA9IHNlcXVlbmNlKFxuXHRcdFx0W2V4cHJlc3Npb24sIG5Pck1vcmUoMCwgbm90KSwgd2hpdGVzcGFjZSwgY2hvaWNlKFtfaW5fLCBfZXF1YWxfXSksIHdoaXRlc3BhY2UsIHJhbmdlTGlzdF1cblx0XHQpO1xuXG5cdFx0aWYgKHJlc3VsdCAhPT0gbnVsbCkge1xuXHRcdFx0ZGVidWcoJyAtLSBwYXNzZWQgX2luOicgKyByZXN1bHQpO1xuXG5cdFx0XHRyYW5nZV9saXN0ID0gcmVzdWx0WzVdO1xuXG5cdFx0XHRmb3IgKGkgPSAwOyBpIDwgcmFuZ2VfbGlzdC5sZW5ndGg7IGkrKykge1xuXHRcdFx0XHRpZiAocGFyc2VJbnQocmFuZ2VfbGlzdFtpXSwgMTApID09PSBwYXJzZUludChyZXN1bHRbMF0sIDEwKSkge1xuXHRcdFx0XHRcdHJldHVybiAocmVzdWx0WzFdWzBdICE9PSAnbm90Jyk7XG5cdFx0XHRcdH1cblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIChyZXN1bHRbMV1bMF0gPT09ICdub3QnKTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCBfaW4gJyk7XG5cblx0XHRyZXR1cm4gbnVsbDtcblx0fVxuXG5cdC8qKlxuXHQgKiBUaGUgZGlmZmVyZW5jZSBiZXR3ZWVuIFwiaW5cIiBhbmQgXCJ3aXRoaW5cIiBpcyB0aGF0XG5cdCAqIFwiaW5cIiBvbmx5IGluY2x1ZGVzIGludGVnZXJzIGluIHRoZSBzcGVjaWZpZWQgcmFuZ2UsXG5cdCAqIHdoaWxlIFwid2l0aGluXCIgaW5jbHVkZXMgYWxsIHZhbHVlcy5cblx0ICovXG5cdGZ1bmN0aW9uIHdpdGhpbigpIHtcblx0XHR2YXIgcmFuZ2VfbGlzdCwgcmVzdWx0O1xuXG5cdFx0Ly8gd2l0aGluX3JlbGF0aW9uID0gZXhwciAoJ25vdCcpPyAnd2l0aGluJyByYW5nZV9saXN0XG5cdFx0cmVzdWx0ID0gc2VxdWVuY2UoXG5cdFx0XHRbZXhwcmVzc2lvbiwgbk9yTW9yZSgwLCBub3QpLCB3aGl0ZXNwYWNlLCBfd2l0aGluXywgd2hpdGVzcGFjZSwgcmFuZ2VMaXN0XVxuXHRcdCk7XG5cblx0XHRpZiAocmVzdWx0ICE9PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIHBhc3NlZCB3aXRoaW4nKTtcblxuXHRcdFx0cmFuZ2VfbGlzdCA9IHJlc3VsdFs1XTtcblxuXHRcdFx0aWYgKChyZXN1bHRbMF0gPj0gcGFyc2VJbnQocmFuZ2VfbGlzdFswXSwgMTApKSAmJlxuXHRcdFx0XHQocmVzdWx0WzBdIDwgcGFyc2VJbnQocmFuZ2VfbGlzdFtyYW5nZV9saXN0Lmxlbmd0aCAtIDFdLCAxMCkpKSB7XG5cblx0XHRcdFx0cmV0dXJuIChyZXN1bHRbMV1bMF0gIT09ICdub3QnKTtcblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIChyZXN1bHRbMV1bMF0gPT09ICdub3QnKTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCB3aXRoaW4gJyk7XG5cblx0XHRyZXR1cm4gbnVsbDtcblx0fVxuXG5cdC8vIHJlbGF0aW9uICAgICAgPSBpc19yZWxhdGlvbiB8IGluX3JlbGF0aW9uIHwgd2l0aGluX3JlbGF0aW9uXG5cdHJlbGF0aW9uID0gY2hvaWNlKFtpcywgbm90X2luLCBpc25vdCwgX2luLCB3aXRoaW5dKTtcblxuXHQvLyBhbmRfY29uZGl0aW9uID0gcmVsYXRpb24gKCdhbmQnIHJlbGF0aW9uKSpcblx0ZnVuY3Rpb24gYW5kKCkge1xuXHRcdHZhciBpLFxuXHRcdFx0cmVzdWx0ID0gc2VxdWVuY2UoW3JlbGF0aW9uLCBuT3JNb3JlKDAsIGFuZFRhaWwpXSk7XG5cblx0XHRpZiAocmVzdWx0KSB7XG5cdFx0XHRpZiAoIXJlc3VsdFswXSkge1xuXHRcdFx0XHRyZXR1cm4gZmFsc2U7XG5cdFx0XHR9XG5cblx0XHRcdGZvciAoaSA9IDA7IGkgPCByZXN1bHRbMV0ubGVuZ3RoOyBpKyspIHtcblx0XHRcdFx0aWYgKCFyZXN1bHRbMV1baV0pIHtcblx0XHRcdFx0XHRyZXR1cm4gZmFsc2U7XG5cdFx0XHRcdH1cblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIHRydWU7XG5cdFx0fVxuXG5cdFx0ZGVidWcoJyAtLSBmYWlsZWQgYW5kJyk7XG5cblx0XHRyZXR1cm4gbnVsbDtcblx0fVxuXG5cdC8vICgnYW5kJyByZWxhdGlvbikqXG5cdGZ1bmN0aW9uIGFuZFRhaWwoKSB7XG5cdFx0dmFyIHJlc3VsdCA9IHNlcXVlbmNlKFt3aGl0ZXNwYWNlLCBfYW5kXywgd2hpdGVzcGFjZSwgcmVsYXRpb25dKTtcblxuXHRcdGlmIChyZXN1bHQgIT09IG51bGwpIHtcblx0XHRcdGRlYnVnKCcgLS0gcGFzc2VkIGFuZFRhaWwnICsgcmVzdWx0KTtcblxuXHRcdFx0cmV0dXJuIHJlc3VsdFszXTtcblx0XHR9XG5cblx0XHRkZWJ1ZygnIC0tIGZhaWxlZCBhbmRUYWlsJyk7XG5cblx0XHRyZXR1cm4gbnVsbDtcblxuXHR9XG5cdC8vICAoJ29yJyBhbmRfY29uZGl0aW9uKSpcblx0ZnVuY3Rpb24gb3JUYWlsKCkge1xuXHRcdHZhciByZXN1bHQgPSBzZXF1ZW5jZShbd2hpdGVzcGFjZSwgX29yXywgd2hpdGVzcGFjZSwgYW5kXSk7XG5cblx0XHRpZiAocmVzdWx0ICE9PSBudWxsKSB7XG5cdFx0XHRkZWJ1ZygnIC0tIHBhc3NlZCBvclRhaWw6ICcgKyByZXN1bHRbM10pO1xuXG5cdFx0XHRyZXR1cm4gcmVzdWx0WzNdO1xuXHRcdH1cblxuXHRcdGRlYnVnKCcgLS0gZmFpbGVkIG9yVGFpbCcpO1xuXG5cdFx0cmV0dXJuIG51bGw7XG5cdH1cblxuXHQvLyBjb25kaXRpb24gICAgID0gYW5kX2NvbmRpdGlvbiAoJ29yJyBhbmRfY29uZGl0aW9uKSpcblx0ZnVuY3Rpb24gY29uZGl0aW9uKCkge1xuXHRcdHZhciBpLFxuXHRcdFx0cmVzdWx0ID0gc2VxdWVuY2UoW2FuZCwgbk9yTW9yZSgwLCBvclRhaWwpXSk7XG5cblx0XHRpZiAocmVzdWx0KSB7XG5cdFx0XHRmb3IgKGkgPSAwOyBpIDwgcmVzdWx0WzFdLmxlbmd0aDsgaSsrKSB7XG5cdFx0XHRcdGlmIChyZXN1bHRbMV1baV0pIHtcblx0XHRcdFx0XHRyZXR1cm4gdHJ1ZTtcblx0XHRcdFx0fVxuXHRcdFx0fVxuXG5cdFx0XHRyZXR1cm4gcmVzdWx0WzBdO1xuXHRcdH1cblxuXHRcdHJldHVybiBmYWxzZTtcblx0fVxuXG5cdHJlc3VsdCA9IGNvbmRpdGlvbigpO1xuXG5cdC8qKlxuXHQgKiBGb3Igc3VjY2VzcywgdGhlIHBvcyBtdXN0IGhhdmUgZ290dGVuIHRvIHRoZSBlbmQgb2YgdGhlIHJ1bGVcblx0ICogYW5kIHJldHVybmVkIGEgbm9uLW51bGwuXG5cdCAqIG4uYi4gVGhpcyBpcyBwYXJ0IG9mIGxhbmd1YWdlIGluZnJhc3RydWN0dXJlLFxuXHQgKiBzbyB3ZSBkbyBub3QgdGhyb3cgYW4gaW50ZXJuYXRpb25hbGl6YWJsZSBtZXNzYWdlLlxuXHQgKi9cblx0aWYgKHJlc3VsdCA9PT0gbnVsbCkge1xuXHRcdHRocm93IG5ldyBFcnJvcignUGFyc2UgZXJyb3IgYXQgcG9zaXRpb24gJyArIHBvcy50b1N0cmluZygpICsgJyBmb3IgcnVsZTogJyArIHJ1bGUpO1xuXHR9XG5cblx0aWYgKHBvcyAhPT0gcnVsZS5sZW5ndGgpIHtcblx0XHRkZWJ1ZygnV2FybmluZzogUnVsZSBub3QgcGFyc2VkIGNvbXBsZXRlbHkuIFBhcnNlciBzdG9wcGVkIGF0ICcgKyBydWxlLnN1YnN0cigwLCBwb3MpICsgJyBmb3IgcnVsZTogJyArIHJ1bGUpO1xuXHR9XG5cblx0cmV0dXJuIHJlc3VsdDtcbn1cblxucmV0dXJuIHBsdXJhbFJ1bGVQYXJzZXI7XG5cbn0pKTtcbiIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsInZhciBtYXAgPSB7XG5cdFwiLi9hZlwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYWYuanNcIixcblx0XCIuL2FmLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hZi5qc1wiLFxuXHRcIi4vYXJcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2FyLmpzXCIsXG5cdFwiLi9hci1kelwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYXItZHouanNcIixcblx0XCIuL2FyLWR6LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hci1kei5qc1wiLFxuXHRcIi4vYXIta3dcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2FyLWt3LmpzXCIsXG5cdFwiLi9hci1rdy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYXIta3cuanNcIixcblx0XCIuL2FyLWx5XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hci1seS5qc1wiLFxuXHRcIi4vYXItbHkuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2FyLWx5LmpzXCIsXG5cdFwiLi9hci1tYVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYXItbWEuanNcIixcblx0XCIuL2FyLW1hLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hci1tYS5qc1wiLFxuXHRcIi4vYXItc2FcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2FyLXNhLmpzXCIsXG5cdFwiLi9hci1zYS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYXItc2EuanNcIixcblx0XCIuL2FyLXRuXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hci10bi5qc1wiLFxuXHRcIi4vYXItdG4uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2FyLXRuLmpzXCIsXG5cdFwiLi9hci5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYXIuanNcIixcblx0XCIuL2F6XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9hei5qc1wiLFxuXHRcIi4vYXouanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2F6LmpzXCIsXG5cdFwiLi9iZVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYmUuanNcIixcblx0XCIuL2JlLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9iZS5qc1wiLFxuXHRcIi4vYmdcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2JnLmpzXCIsXG5cdFwiLi9iZy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYmcuanNcIixcblx0XCIuL2JtXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ibS5qc1wiLFxuXHRcIi4vYm0uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2JtLmpzXCIsXG5cdFwiLi9iblwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYm4uanNcIixcblx0XCIuL2JuLWJkXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ibi1iZC5qc1wiLFxuXHRcIi4vYm4tYmQuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2JuLWJkLmpzXCIsXG5cdFwiLi9ibi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYm4uanNcIixcblx0XCIuL2JvXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9iby5qc1wiLFxuXHRcIi4vYm8uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2JvLmpzXCIsXG5cdFwiLi9iclwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYnIuanNcIixcblx0XCIuL2JyLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ici5qc1wiLFxuXHRcIi4vYnNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2JzLmpzXCIsXG5cdFwiLi9icy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvYnMuanNcIixcblx0XCIuL2NhXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9jYS5qc1wiLFxuXHRcIi4vY2EuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2NhLmpzXCIsXG5cdFwiLi9jc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvY3MuanNcIixcblx0XCIuL2NzLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9jcy5qc1wiLFxuXHRcIi4vY3ZcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2N2LmpzXCIsXG5cdFwiLi9jdi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvY3YuanNcIixcblx0XCIuL2N5XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9jeS5qc1wiLFxuXHRcIi4vY3kuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2N5LmpzXCIsXG5cdFwiLi9kYVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZGEuanNcIixcblx0XCIuL2RhLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9kYS5qc1wiLFxuXHRcIi4vZGVcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2RlLmpzXCIsXG5cdFwiLi9kZS1hdFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZGUtYXQuanNcIixcblx0XCIuL2RlLWF0LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9kZS1hdC5qc1wiLFxuXHRcIi4vZGUtY2hcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2RlLWNoLmpzXCIsXG5cdFwiLi9kZS1jaC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZGUtY2guanNcIixcblx0XCIuL2RlLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9kZS5qc1wiLFxuXHRcIi4vZHZcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2R2LmpzXCIsXG5cdFwiLi9kdi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZHYuanNcIixcblx0XCIuL2VsXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbC5qc1wiLFxuXHRcIi4vZWwuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VsLmpzXCIsXG5cdFwiLi9lbi1hdVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4tYXUuanNcIixcblx0XCIuL2VuLWF1LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbi1hdS5qc1wiLFxuXHRcIi4vZW4tY2FcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VuLWNhLmpzXCIsXG5cdFwiLi9lbi1jYS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4tY2EuanNcIixcblx0XCIuL2VuLWdiXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbi1nYi5qc1wiLFxuXHRcIi4vZW4tZ2IuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VuLWdiLmpzXCIsXG5cdFwiLi9lbi1pZVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4taWUuanNcIixcblx0XCIuL2VuLWllLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbi1pZS5qc1wiLFxuXHRcIi4vZW4taWxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VuLWlsLmpzXCIsXG5cdFwiLi9lbi1pbC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4taWwuanNcIixcblx0XCIuL2VuLWluXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbi1pbi5qc1wiLFxuXHRcIi4vZW4taW4uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VuLWluLmpzXCIsXG5cdFwiLi9lbi1uelwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4tbnouanNcIixcblx0XCIuL2VuLW56LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lbi1uei5qc1wiLFxuXHRcIi4vZW4tc2dcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VuLXNnLmpzXCIsXG5cdFwiLi9lbi1zZy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZW4tc2cuanNcIixcblx0XCIuL2VvXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lby5qc1wiLFxuXHRcIi4vZW8uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VvLmpzXCIsXG5cdFwiLi9lc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZXMuanNcIixcblx0XCIuL2VzLWRvXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lcy1kby5qc1wiLFxuXHRcIi4vZXMtZG8uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VzLWRvLmpzXCIsXG5cdFwiLi9lcy1teFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZXMtbXguanNcIixcblx0XCIuL2VzLW14LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lcy1teC5qc1wiLFxuXHRcIi4vZXMtdXNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2VzLXVzLmpzXCIsXG5cdFwiLi9lcy11cy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZXMtdXMuanNcIixcblx0XCIuL2VzLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9lcy5qc1wiLFxuXHRcIi4vZXRcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2V0LmpzXCIsXG5cdFwiLi9ldC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZXQuanNcIixcblx0XCIuL2V1XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ldS5qc1wiLFxuXHRcIi4vZXUuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2V1LmpzXCIsXG5cdFwiLi9mYVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZmEuanNcIixcblx0XCIuL2ZhLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9mYS5qc1wiLFxuXHRcIi4vZmlcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ZpLmpzXCIsXG5cdFwiLi9maS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZmkuanNcIixcblx0XCIuL2ZpbFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZmlsLmpzXCIsXG5cdFwiLi9maWwuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ZpbC5qc1wiLFxuXHRcIi4vZm9cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ZvLmpzXCIsXG5cdFwiLi9mby5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZm8uanNcIixcblx0XCIuL2ZyXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9mci5qc1wiLFxuXHRcIi4vZnItY2FcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ZyLWNhLmpzXCIsXG5cdFwiLi9mci1jYS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZnItY2EuanNcIixcblx0XCIuL2ZyLWNoXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9mci1jaC5qc1wiLFxuXHRcIi4vZnItY2guanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ZyLWNoLmpzXCIsXG5cdFwiLi9mci5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZnIuanNcIixcblx0XCIuL2Z5XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9meS5qc1wiLFxuXHRcIi4vZnkuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2Z5LmpzXCIsXG5cdFwiLi9nYVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZ2EuanNcIixcblx0XCIuL2dhLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9nYS5qc1wiLFxuXHRcIi4vZ2RcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2dkLmpzXCIsXG5cdFwiLi9nZC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZ2QuanNcIixcblx0XCIuL2dsXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9nbC5qc1wiLFxuXHRcIi4vZ2wuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2dsLmpzXCIsXG5cdFwiLi9nb20tZGV2YVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZ29tLWRldmEuanNcIixcblx0XCIuL2dvbS1kZXZhLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9nb20tZGV2YS5qc1wiLFxuXHRcIi4vZ29tLWxhdG5cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2dvbS1sYXRuLmpzXCIsXG5cdFwiLi9nb20tbGF0bi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvZ29tLWxhdG4uanNcIixcblx0XCIuL2d1XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ndS5qc1wiLFxuXHRcIi4vZ3UuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2d1LmpzXCIsXG5cdFwiLi9oZVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaGUuanNcIixcblx0XCIuL2hlLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9oZS5qc1wiLFxuXHRcIi4vaGlcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2hpLmpzXCIsXG5cdFwiLi9oaS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaGkuanNcIixcblx0XCIuL2hyXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9oci5qc1wiLFxuXHRcIi4vaHIuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2hyLmpzXCIsXG5cdFwiLi9odVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaHUuanNcIixcblx0XCIuL2h1LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9odS5qc1wiLFxuXHRcIi4vaHktYW1cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2h5LWFtLmpzXCIsXG5cdFwiLi9oeS1hbS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaHktYW0uanNcIixcblx0XCIuL2lkXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9pZC5qc1wiLFxuXHRcIi4vaWQuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2lkLmpzXCIsXG5cdFwiLi9pc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaXMuanNcIixcblx0XCIuL2lzLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9pcy5qc1wiLFxuXHRcIi4vaXRcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2l0LmpzXCIsXG5cdFwiLi9pdC1jaFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvaXQtY2guanNcIixcblx0XCIuL2l0LWNoLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9pdC1jaC5qc1wiLFxuXHRcIi4vaXQuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2l0LmpzXCIsXG5cdFwiLi9qYVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvamEuanNcIixcblx0XCIuL2phLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9qYS5qc1wiLFxuXHRcIi4vanZcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2p2LmpzXCIsXG5cdFwiLi9qdi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvanYuanNcIixcblx0XCIuL2thXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9rYS5qc1wiLFxuXHRcIi4va2EuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2thLmpzXCIsXG5cdFwiLi9ra1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUva2suanNcIixcblx0XCIuL2trLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ray5qc1wiLFxuXHRcIi4va21cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2ttLmpzXCIsXG5cdFwiLi9rbS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUva20uanNcIixcblx0XCIuL2tuXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9rbi5qc1wiLFxuXHRcIi4va24uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2tuLmpzXCIsXG5cdFwiLi9rb1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUva28uanNcIixcblx0XCIuL2tvLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9rby5qc1wiLFxuXHRcIi4va3VcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2t1LmpzXCIsXG5cdFwiLi9rdS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUva3UuanNcIixcblx0XCIuL2t5XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9reS5qc1wiLFxuXHRcIi4va3kuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2t5LmpzXCIsXG5cdFwiLi9sYlwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbGIuanNcIixcblx0XCIuL2xiLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9sYi5qc1wiLFxuXHRcIi4vbG9cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2xvLmpzXCIsXG5cdFwiLi9sby5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbG8uanNcIixcblx0XCIuL2x0XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9sdC5qc1wiLFxuXHRcIi4vbHQuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL2x0LmpzXCIsXG5cdFwiLi9sdlwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbHYuanNcIixcblx0XCIuL2x2LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9sdi5qc1wiLFxuXHRcIi4vbWVcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21lLmpzXCIsXG5cdFwiLi9tZS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbWUuanNcIixcblx0XCIuL21pXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9taS5qc1wiLFxuXHRcIi4vbWkuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21pLmpzXCIsXG5cdFwiLi9ta1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbWsuanNcIixcblx0XCIuL21rLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9tay5qc1wiLFxuXHRcIi4vbWxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21sLmpzXCIsXG5cdFwiLi9tbC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbWwuanNcIixcblx0XCIuL21uXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9tbi5qc1wiLFxuXHRcIi4vbW4uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21uLmpzXCIsXG5cdFwiLi9tclwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbXIuanNcIixcblx0XCIuL21yLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9tci5qc1wiLFxuXHRcIi4vbXNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21zLmpzXCIsXG5cdFwiLi9tcy1teVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbXMtbXkuanNcIixcblx0XCIuL21zLW15LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9tcy1teS5qc1wiLFxuXHRcIi4vbXMuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL21zLmpzXCIsXG5cdFwiLi9tdFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbXQuanNcIixcblx0XCIuL210LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9tdC5qc1wiLFxuXHRcIi4vbXlcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL215LmpzXCIsXG5cdFwiLi9teS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbXkuanNcIixcblx0XCIuL25iXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9uYi5qc1wiLFxuXHRcIi4vbmIuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL25iLmpzXCIsXG5cdFwiLi9uZVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbmUuanNcIixcblx0XCIuL25lLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9uZS5qc1wiLFxuXHRcIi4vbmxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL25sLmpzXCIsXG5cdFwiLi9ubC1iZVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbmwtYmUuanNcIixcblx0XCIuL25sLWJlLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ubC1iZS5qc1wiLFxuXHRcIi4vbmwuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL25sLmpzXCIsXG5cdFwiLi9ublwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvbm4uanNcIixcblx0XCIuL25uLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ubi5qc1wiLFxuXHRcIi4vb2MtbG5jXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9vYy1sbmMuanNcIixcblx0XCIuL29jLWxuYy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvb2MtbG5jLmpzXCIsXG5cdFwiLi9wYS1pblwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvcGEtaW4uanNcIixcblx0XCIuL3BhLWluLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9wYS1pbi5qc1wiLFxuXHRcIi4vcGxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3BsLmpzXCIsXG5cdFwiLi9wbC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvcGwuanNcIixcblx0XCIuL3B0XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9wdC5qc1wiLFxuXHRcIi4vcHQtYnJcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3B0LWJyLmpzXCIsXG5cdFwiLi9wdC1ici5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvcHQtYnIuanNcIixcblx0XCIuL3B0LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9wdC5qc1wiLFxuXHRcIi4vcm9cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3JvLmpzXCIsXG5cdFwiLi9yby5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvcm8uanNcIixcblx0XCIuL3J1XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9ydS5qc1wiLFxuXHRcIi4vcnUuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3J1LmpzXCIsXG5cdFwiLi9zZFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc2QuanNcIixcblx0XCIuL3NkLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zZC5qc1wiLFxuXHRcIi4vc2VcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NlLmpzXCIsXG5cdFwiLi9zZS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc2UuanNcIixcblx0XCIuL3NpXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zaS5qc1wiLFxuXHRcIi4vc2kuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NpLmpzXCIsXG5cdFwiLi9za1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc2suanNcIixcblx0XCIuL3NrLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zay5qc1wiLFxuXHRcIi4vc2xcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NsLmpzXCIsXG5cdFwiLi9zbC5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc2wuanNcIixcblx0XCIuL3NxXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zcS5qc1wiLFxuXHRcIi4vc3EuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NxLmpzXCIsXG5cdFwiLi9zclwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc3IuanNcIixcblx0XCIuL3NyLWN5cmxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NyLWN5cmwuanNcIixcblx0XCIuL3NyLWN5cmwuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NyLWN5cmwuanNcIixcblx0XCIuL3NyLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zci5qc1wiLFxuXHRcIi4vc3NcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3NzLmpzXCIsXG5cdFwiLi9zcy5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc3MuanNcIixcblx0XCIuL3N2XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zdi5qc1wiLFxuXHRcIi4vc3YuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3N2LmpzXCIsXG5cdFwiLi9zd1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvc3cuanNcIixcblx0XCIuL3N3LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS9zdy5qc1wiLFxuXHRcIi4vdGFcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RhLmpzXCIsXG5cdFwiLi90YS5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdGEuanNcIixcblx0XCIuL3RlXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90ZS5qc1wiLFxuXHRcIi4vdGUuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RlLmpzXCIsXG5cdFwiLi90ZXRcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RldC5qc1wiLFxuXHRcIi4vdGV0LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90ZXQuanNcIixcblx0XCIuL3RnXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90Zy5qc1wiLFxuXHRcIi4vdGcuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RnLmpzXCIsXG5cdFwiLi90aFwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdGguanNcIixcblx0XCIuL3RoLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90aC5qc1wiLFxuXHRcIi4vdGtcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RrLmpzXCIsXG5cdFwiLi90ay5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdGsuanNcIixcblx0XCIuL3RsLXBoXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90bC1waC5qc1wiLFxuXHRcIi4vdGwtcGguanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RsLXBoLmpzXCIsXG5cdFwiLi90bGhcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RsaC5qc1wiLFxuXHRcIi4vdGxoLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90bGguanNcIixcblx0XCIuL3RyXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90ci5qc1wiLFxuXHRcIi4vdHIuanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3RyLmpzXCIsXG5cdFwiLi90emxcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3R6bC5qc1wiLFxuXHRcIi4vdHpsLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90emwuanNcIixcblx0XCIuL3R6bVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdHptLmpzXCIsXG5cdFwiLi90em0tbGF0blwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdHptLWxhdG4uanNcIixcblx0XCIuL3R6bS1sYXRuLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90em0tbGF0bi5qc1wiLFxuXHRcIi4vdHptLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS90em0uanNcIixcblx0XCIuL3VnLWNuXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS91Zy1jbi5qc1wiLFxuXHRcIi4vdWctY24uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3VnLWNuLmpzXCIsXG5cdFwiLi91a1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdWsuanNcIixcblx0XCIuL3VrLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS91ay5qc1wiLFxuXHRcIi4vdXJcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3VyLmpzXCIsXG5cdFwiLi91ci5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdXIuanNcIixcblx0XCIuL3V6XCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS91ei5qc1wiLFxuXHRcIi4vdXotbGF0blwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdXotbGF0bi5qc1wiLFxuXHRcIi4vdXotbGF0bi5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdXotbGF0bi5qc1wiLFxuXHRcIi4vdXouanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3V6LmpzXCIsXG5cdFwiLi92aVwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvdmkuanNcIixcblx0XCIuL3ZpLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS92aS5qc1wiLFxuXHRcIi4veC1wc2V1ZG9cIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3gtcHNldWRvLmpzXCIsXG5cdFwiLi94LXBzZXVkby5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUveC1wc2V1ZG8uanNcIixcblx0XCIuL3lvXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS95by5qc1wiLFxuXHRcIi4veW8uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3lvLmpzXCIsXG5cdFwiLi96aC1jblwiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvemgtY24uanNcIixcblx0XCIuL3poLWNuLmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS96aC1jbi5qc1wiLFxuXHRcIi4vemgtaGtcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3poLWhrLmpzXCIsXG5cdFwiLi96aC1oay5qc1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvemgtaGsuanNcIixcblx0XCIuL3poLW1vXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS96aC1tby5qc1wiLFxuXHRcIi4vemgtbW8uanNcIjogXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlL3poLW1vLmpzXCIsXG5cdFwiLi96aC10d1wiOiBcIi4vbm9kZV9tb2R1bGVzL21vbWVudC9sb2NhbGUvemgtdHcuanNcIixcblx0XCIuL3poLXR3LmpzXCI6IFwiLi9ub2RlX21vZHVsZXMvbW9tZW50L2xvY2FsZS96aC10dy5qc1wiXG59O1xuXG5cbmZ1bmN0aW9uIHdlYnBhY2tDb250ZXh0KHJlcSkge1xuXHR2YXIgaWQgPSB3ZWJwYWNrQ29udGV4dFJlc29sdmUocmVxKTtcblx0cmV0dXJuIF9fd2VicGFja19yZXF1aXJlX18oaWQpO1xufVxuZnVuY3Rpb24gd2VicGFja0NvbnRleHRSZXNvbHZlKHJlcSkge1xuXHRpZighX193ZWJwYWNrX3JlcXVpcmVfXy5vKG1hcCwgcmVxKSkge1xuXHRcdHZhciBlID0gbmV3IEVycm9yKFwiQ2Fubm90IGZpbmQgbW9kdWxlICdcIiArIHJlcSArIFwiJ1wiKTtcblx0XHRlLmNvZGUgPSAnTU9EVUxFX05PVF9GT1VORCc7XG5cdFx0dGhyb3cgZTtcblx0fVxuXHRyZXR1cm4gbWFwW3JlcV07XG59XG53ZWJwYWNrQ29udGV4dC5rZXlzID0gZnVuY3Rpb24gd2VicGFja0NvbnRleHRLZXlzKCkge1xuXHRyZXR1cm4gT2JqZWN0LmtleXMobWFwKTtcbn07XG53ZWJwYWNrQ29udGV4dC5yZXNvbHZlID0gd2VicGFja0NvbnRleHRSZXNvbHZlO1xubW9kdWxlLmV4cG9ydHMgPSB3ZWJwYWNrQ29udGV4dDtcbndlYnBhY2tDb250ZXh0LmlkID0gXCIuL25vZGVfbW9kdWxlcy9tb21lbnQvbG9jYWxlIHN5bmMgcmVjdXJzaXZlIF5cXFxcLlxcXFwvLiokXCI7Il0sIm5hbWVzIjpbInh0b29scyIsImFkbWluc3RhdHMiLCIkIiwiJHByb2plY3RJbnB1dCIsImxhc3RQcm9qZWN0IiwidmFsIiwibGVuZ3RoIiwiYXBwbGljYXRpb24iLCJzZXR1cE11bHRpU2VsZWN0TGlzdGVuZXJzIiwib24iLCJhZGRDbGFzcyIsInJlbW92ZUNsYXNzIiwidGV4dCIsImkxOG4iLCJ0aXRsZSIsImRvY3VtZW50IiwiaGlzdG9yeSIsInJlcGxhY2VTdGF0ZSIsImFydGljbGVpbmZvIiwic2V0dXBUb2dnbGVUYWJsZSIsIndpbmRvdyIsInRleHRzaGFyZXMiLCJ0ZXh0c2hhcmVzQ2hhcnQiLCJub29wIiwiJHRleHRzaGFyZXNDb250YWluZXIiLCJ1cmwiLCJ4dEJhc2VVcmwiLCJkYXRhIiwiY29uY2F0IiwicmVwbGFjZSIsImFqYXgiLCJ0aW1lb3V0IiwiZG9uZSIsInJlcGxhY2VXaXRoIiwiYnVpbGRTZWN0aW9uT2Zmc2V0cyIsInNldHVwVG9jTGlzdGVuZXJzIiwic2V0dXBDb2x1bW5Tb3J0aW5nIiwiZmFpbCIsIl94aHIiLCJfc3RhdHVzIiwibWVzc2FnZSIsIiRjaGFydCIsImRhdGFzZXRzIiwiQ2hhcnQiLCJ0eXBlIiwibGFiZWxzIiwib3B0aW9ucyIsInJlc3BvbnNpdmUiLCJsZWdlbmQiLCJkaXNwbGF5IiwidG9vbHRpcHMiLCJtb2RlIiwiY2FsbGJhY2tzIiwibGFiZWwiLCJ0b29sdGlwSXRlbSIsImRhdGFzZXRJbmRleCIsIk51bWJlciIsInlMYWJlbCIsInRvTG9jYWxlU3RyaW5nIiwiaTE4bkxhbmciLCJiYXJWYWx1ZVNwYWNpbmciLCJzY2FsZXMiLCJ5QXhlcyIsImlkIiwicG9zaXRpb24iLCJzY2FsZUxhYmVsIiwibGFiZWxTdHJpbmciLCJjYXBpdGFsaXplIiwidGlja3MiLCJiZWdpbkF0WmVybyIsImNhbGxiYWNrIiwidmFsdWUiLCJNYXRoIiwiZmxvb3IiLCJncmlkTGluZXMiLCJjb2xvciIsImNoYXJ0R3JpZENvbG9yIiwieEF4ZXMiLCIkc2hvd1NlbGVjdG9yIiwiZSIsImZpbmQiLCJwcm9wIiwidGFyZ2V0Iiwib25sb2FkIiwidHJpZ2dlciIsInNldHVwQ2hhcnQiLCJwZXJjZW50YWdlcyIsIk9iamVjdCIsImtleXMiLCJzbGljZSIsIm1hcCIsImF1dGhvciIsInBlcmNlbnRhZ2UiLCJwdXNoIiwiYXV0aG9yc2hpcENoYXJ0IiwiYmFja2dyb3VuZENvbG9yIiwiYm9yZGVyQ29sb3IiLCJib3JkZXJXaWR0aCIsImFzcGVjdFJhdGlvIiwiY2hhcnREYXRhIiwiaW5kZXgiLCJzdHlsZSIsIm1heGltdW1GcmFjdGlvbkRpZ2l0cyIsImF1dG9lZGl0cyIsIiRjb250cmlidXRpb25zQ29udGFpbmVyIiwiJHRvb2xTZWxlY3RvciIsImZldGNoVG9vbHMiLCJwcm9qZWN0IiwiZ2V0IiwidG9vbHMiLCJlcnJvciIsImVsYXBzZWRfdGltZSIsImh0bWwiLCJmb3JFYWNoIiwidG9vbCIsImFwcGVuZCIsInJlYWR5IiwiY291bnRzQnlUb29sIiwidG9vbHNDaGFydCIsIm5ld0RhdGEiLCJ0b3RhbCIsInBhcnNlSW50IiwiY291bnQiLCJ0b29sc0NvdW50IiwiaW5pdEZ1bmMiLCJwYXJhbXMiLCJ1c2VybmFtZSIsIm5hbWVzcGFjZSIsInN0YXJ0IiwiZW5kIiwiYmxhbWUiLCJlcSIsInRvTG93ZXJDYXNlIiwiZWFjaCIsImVzY2FwZWRRdWVyeSIsInF1ZXJ5IiwiaGlnaGxpZ2h0TWF0Y2giLCJzZWxlY3RvciIsInJlZ2V4IiwiUmVnRXhwIiwiY2F0ZWdvcnllZGl0cyIsIiRzZWxlY3QySW5wdXQiLCJzZXR1cENhdGVnb3J5SW5wdXQiLCJfZSIsImFwaSIsIm5hbWVzcGFjZXMiLCJqb2luIiwiY291bnRzQnlDYXRlZ29yeSIsImNhdGVnb3J5Q2hhcnQiLCJ0b3RhbEVkaXRzIiwidG90YWxQYWdlcyIsImNhdGVnb3J5IiwiZWRpdENvdW50IiwicGFnZUNvdW50IiwiY2F0ZWdvcmllc0NvdW50IiwidXNlckVkaXRDb3VudCIsImxvYWRDYXRlZ29yeUVkaXRzIiwiY2F0ZWdvcmllcyIsIm5zIiwib2ZmIiwic2VsZWN0MiIsIm5zTmFtZSIsImRhdGFUeXBlIiwianNvbnBDYWxsYmFjayIsImRlbGF5Iiwic2VhcmNoIiwiYWN0aW9uIiwibGlzdCIsImZvcm1hdCIsInBzc2VhcmNoIiwidGVybSIsInBzbmFtZXNwYWNlIiwiY2lycnVzVXNlQ29tcGxldGlvblN1Z2dlc3RlciIsInByb2Nlc3NSZXN1bHRzIiwicmVzdWx0cyIsInByZWZpeHNlYXJjaCIsImVsZW0iLCJzY29yZSIsInBsYWNlaG9sZGVyIiwibWF4aW11bVNlbGVjdGlvbkxlbmd0aCIsIm1pbmltdW1JbnB1dExlbmd0aCIsInJlcXVpcmUiLCJ2YXJzIiwic2VjdGlvbk9mZnNldCIsImdsb2JhbCIsImpRdWVyeSIsIm1hdGNoTWVkaWEiLCJtYXRjaGVzIiwiZGVmYXVsdHMiLCJkZWZhdWx0Rm9udENvbG9yIiwibG9jYWxlIiwibG9hZCIsImkxOG5QYXRocyIsImhpZGUiLCJzaWJsaW5ncyIsInNob3ciLCJwYXJlbnRzIiwibmV4dCIsInNldHVwTmF2Q29sbGFwc2luZyIsInNldHVwVE9DIiwic2V0dXBTdGlja3lIZWFkZXIiLCJzZXR1cFByb2plY3RMaXN0ZW5lciIsInNldHVwQXV0b2NvbXBsZXRpb24iLCJkaXNwbGF5V2FpdGluZ05vdGljZU9uU3VibWlzc2lvbiIsInNldHVwUGllQ2hhcnRzIiwiVVJMIiwiZm9jdXNFbGVtZW50IiwibG9jYXRpb24iLCJocmVmIiwic2VhcmNoUGFyYW1zIiwiZm9jdXMiLCJvbnBhZ2VzaG93IiwicGVyc2lzdGVkIiwiZGF0YVNvdXJjZSIsImNoYXJ0T2JqIiwidmFsdWVLZXkiLCJ1cGRhdGVDYWxsYmFjayIsInRvZ2dsZVRhYmxlRGF0YSIsImFzc2lnbiIsImtleSIsImF0dHIiLCJ0b2dnbGVDbGFzcyIsInVwZGF0ZSIsIndpbmRvd1dpZHRoIiwid2lkdGgiLCJ0b29sTmF2V2lkdGgiLCJvdXRlcldpZHRoIiwibmF2UmlnaHRXaWR0aCIsIm51bUxpbmtzIiwiJGxpbmsiLCJsYXN0IiwicmVtb3ZlIiwic29ydERpcmVjdGlvbiIsInNvcnRDb2x1bW4iLCJuZXdTb3J0Q2xhc3NOYW1lIiwiJHRhYmxlIiwiJGVudHJpZXMiLCJwYXJlbnQiLCJzb3J0IiwiYSIsImIiLCJiZWZvcmUiLCJhZnRlciIsImlzTmFOIiwicGFyc2VGbG9hdCIsImVudHJ5IiwiJHRvYyIsInRvY0hlaWdodCIsImhlaWdodCIsImFjdGl2ZUVsZW1lbnQiLCJibHVyIiwiJG5ld1NlY3Rpb24iLCJzY3JvbGxUb3AiLCJvZmZzZXQiLCJ0b3AiLCJjcmVhdGVUb2NDbG9uZSIsIiR0b2NDbG9uZSIsImNsb25lIiwidG9jTWVtYmVyIiwidG9jT2Zmc2V0VG9wIiwid2luZG93T2Zmc2V0IiwiaW5SYW5nZSIsIiRhY3RpdmVNZW1iZXIiLCJzZWN0aW9uIiwiJGhlYWRlciIsIiRoZWFkZXJSb3ciLCIkaGVhZGVyQ2xvbmUiLCJjbG9uZUhlYWRlciIsImNzcyIsImhlYWRlck9mZnNldFRvcCIsInNldHVwTmFtZXNwYWNlU2VsZWN0b3IiLCJuZXdQcm9qZWN0IiwiYXBpUGF0aCIsInJldmVydFRvVmFsaWRQcm9qZWN0IiwiYmluZCIsIiRhbGxPcHRpb24iLCJoYXNPd25Qcm9wZXJ0eSIsImFsd2F5cyIsIiRhcnRpY2xlSW5wdXQiLCIkdXNlcklucHV0IiwiJG5hbWVzcGFjZUlucHV0IiwiZGVzdHJveSIsInR5cGVhaGVhZE9wdHMiLCJ0cmlnZ2VyTGVuZ3RoIiwibWV0aG9kIiwicHJlRGlzcGF0Y2giLCJwcmVQcm9jZXNzIiwidHlwZWFoZWFkIiwidHJpbSIsInNwbGl0Iiwic3Vic3RyIiwiaW5kZXhPZiIsImZpbHRlciIsImFycmF5IiwidW5kbyIsInN0YXJ0VGltZSIsIkRhdGUiLCJub3ciLCJzZXRJbnRlcnZhbCIsImVsYXBzZWRTZWNvbmRzIiwicm91bmQiLCJtaW51dGVzIiwic2Vjb25kcyIsIiRjaGFydHMiLCIkaW5wdXRzIiwiaW5pdGlhbE9mZnNldCIsInByZXZPZmZzZXRzIiwiaW5pdGlhbExvYWQiLCJzZXRJbml0aWFsT2Zmc2V0IiwibG9hZENvbnRyaWJ1dGlvbnMiLCJlbmRwb2ludEZ1bmMiLCJhcGlUaXRsZSIsIiRjb250cmlidXRpb25zTG9hZGluZyIsImVuZHBvaW50IiwibGltaXQiLCJ1cmxQYXJhbXMiLCJVUkxTZWFyY2hQYXJhbXMiLCJuZXdVcmwiLCJvbGRUb29sUGF0aCIsInBhdGhuYW1lIiwibmV3VG9vbFBhdGgiLCJzZXQiLCJ0b1N0cmluZyIsInNldHVwQ29udHJpYnV0aW9uc05hdkxpc3RlbmVycyIsImZpcnN0IiwicmVnZXhwIiwic2Nyb2xsSW50b1ZpZXciLCJvbmUiLCJwcmV2ZW50RGVmYXVsdCIsInBvcCIsIlN0cmluZyIsInByb3RvdHlwZSIsImRlc2NvcmUiLCJlc2NhcGUiLCJlbnRpdHlNYXAiLCJzIiwiQXJyYXkiLCJ1bmlxdWUiLCJkZWZpbmVQcm9wZXJ0eSIsImNoYXJBdCIsInRvVXBwZXJDYXNlIiwiZW51bWVyYWJsZSIsImVkaXRjb3VudGVyIiwiZXhjbHVkZWROYW1lc3BhY2VzIiwiY2hhcnRMYWJlbHMiLCJtYXhEaWdpdHMiLCJjaGFydFR5cGUiLCJ1bmRlZmluZWQiLCIkY3R4IiwibmFtZXNwYWNlVG90YWxzIiwibmFtZXNwYWNlQ2hhcnQiLCJ0b2dnbGVOYW1lc3BhY2UiLCJjb3VudHMiLCJuYW1lc3BhY2VDb3VudCIsImdldFBlcmNlbnRhZ2UiLCJkYXRhc2V0IiwiaSIsIm1ldGEiLCJnZXREYXRhc2V0TWV0YSIsImhpZGRlbiIsImNvbmZpZyIsImdldFlBeGlzTGFiZWxzIiwibGFiZWxzQW5kVG90YWxzIiwiZ2V0TW9udGhZZWFyVG90YWxzIiwieWVhciIsImRpZ2l0Q291bnQiLCJudW1UYWJzIiwidXNlR3JvdXBpbmciLCJudW1lcmF0b3IiLCJkZW5vbWluYXRvciIsInNldHVwTW9udGhZZWFyQ2hhcnQiLCJtYXhUb3RhbCIsInNob3dMZWdlbmQiLCJpbnRlcnNlY3QiLCJ0b29sdGlwIiwidG90YWxzIiwieExhYmVsIiwibWFpbnRhaW5Bc3BlY3RSYXRpbyIsInN0YWNrZWQiLCJyZXZlcnNlIiwiaTE4blJUTCIsImJhclRoaWNrbmVzcyIsInNldHVwVGltZWNhcmQiLCJ0aW1lQ2FyZERhdGFzZXRzIiwiZGF5cyIsInVzZUxvY2FsVGltZXpvbmUiLCJ0aW1lem9uZU9mZnNldCIsImdldFRpbWV6b25lT2Zmc2V0IiwiY2hhcnQiLCJsYXlvdXQiLCJwYWRkaW5nIiwicmlnaHQiLCJlbGVtZW50cyIsInBvaW50IiwicmFkaXVzIiwiY29udGV4dCIsImRhdGFJbmRleCIsInNjYWxlIiwiaGl0UmFkaXVzIiwibWluIiwibWF4Iiwic3RlcFNpemUiLCJyZWR1Y2UiLCJkaXNwbGF5Q29sb3JzIiwiaXRlbXMiLCJpdGVtIiwibnVtRWRpdHMiLCJpcyIsImRheSIsImRhdHVtIiwibmV3SG91ciIsImhvdXIiLCJ4IiwiZ2xvYmFsY29udHJpYnMiLCJwYWdlcyIsImRlbGV0aW9uU3VtbWFyaWVzIiwiY291bnRzQnlOYW1lc3BhY2UiLCJwaWVDaGFydCIsImRlbGV0ZWQiLCJyZWRpcmVjdHMiLCJ0b0ZpeGVkIiwicGFnZSIsInNob3dTdW1tYXJ5Iiwic3VtbWFyeSIsImxvZ0V2ZW50c1F1ZXJ5Iiwid2lraUFwaSIsImxldGl0bGUiLCJsZXN0YXJ0IiwibGV0eXBlIiwibGVhY3Rpb24iLCJsZWxpbWl0Iiwic2hvd1BhcnNlckFwaUZhaWx1cmUiLCJzaG93TG9nZ2luZ0FwaUZhaWx1cmUiLCJzaG93UGFyc2VkV2lraXRleHQiLCJldmVudCIsIndpa2lEb21haW4iLCJlbmNvZGVVUklDb21wb25lbnQiLCJjb21tZW50IiwibWFya3VwIiwidGltZXN0YW1wIiwidG9JU09TdHJpbmciLCJ1c2VyIiwicmVzcCIsImxvZ2V2ZW50cyIsInRvcGVkaXRzIiwiVHlwZWFoZWFkIiwiZWxlbWVudCIsImRlZmF1bHRPcHRpb25zIiwiZm4iLCJzY3JvbGxCYXIiLCJtZW51IiwidGhhdCIsIiRlbGVtZW50IiwiZXh0ZW5kIiwiJG1lbnUiLCJpbnNlcnRBZnRlciIsImV2ZW50U3VwcG9ydGVkIiwiZ3JlcHBlciIsImhpZ2hsaWdodGVyIiwibG9va3VwIiwibWF0Y2hlciIsInJlbmRlciIsIm9uU2VsZWN0Iiwic29ydGVyIiwic291cmNlIiwiZGlzcGxheUZpZWxkIiwidmFsdWVGaWVsZCIsInNob3duIiwibGlzdGVuIiwiY29uc3RydWN0b3IiLCJldmVudE5hbWUiLCJpc1N1cHBvcnRlZCIsInNldEF0dHJpYnV0ZSIsInNlbGVjdCIsIiRzZWxlY3RlZEl0ZW0iLCJ1cGRhdGVyIiwiY2hhbmdlIiwicG9zIiwib2Zmc2V0SGVpZ2h0IiwibGVmdCIsImFsaWduV2lkdGgiLCJhamF4TG9va3VwIiwidGltZXJJZCIsImNsZWFyVGltZW91dCIsInhociIsImFib3J0IiwiYWpheFRvZ2dsZUxvYWRDbGFzcyIsImV4ZWN1dGUiLCJzdWNjZXNzIiwicHJveHkiLCJhamF4U291cmNlIiwic2V0VGltZW91dCIsImVuYWJsZSIsImxvYWRpbmdDbGFzcyIsImFqYXhlciIsImJlZ2luc3dpdGgiLCJjYXNlU2Vuc2l0aXZlIiwiY2FzZUluc2Vuc2l0aXZlIiwic2hpZnQiLCIkMSIsIm1hdGNoIiwiaXNTdHJpbmciLCJfdHlwZW9mIiwiZ3JlcCIsImFjdGl2ZSIsImNoaWxkcmVuIiwicHJldiIsIiRsaSIsImtleXByZXNzIiwia2V5dXAiLCJrZXlkb3duIiwiY2xpY2siLCJtb3VzZWVudGVyIiwibW91c2VsZWF2ZSIsIm1vdmUiLCJrZXlDb2RlIiwic3RvcFByb3BhZ2F0aW9uIiwic3VwcHJlc3NLZXlQcmVzc1JlcGVhdCIsImluQXJyYXkiLCJmb2N1c2VkIiwibW91c2Vkb3ZlciIsImN1cnJlbnRUYXJnZXQiLCJyZW1vdmVEYXRhIiwib3B0aW9uIiwiJHRoaXMiLCJDb25zdHJ1Y3RvciIsIm5hdiIsIkkxOE4iLCJwYXJzZXIiLCJtZXNzYWdlU3RvcmUiLCJsYW5ndWFnZXMiLCJpbml0IiwibG9jYWxlUGFydHMiLCJsb2NhbGVQYXJ0SW5kZXgiLCJmYWxsYmFja0luZGV4IiwidHJ5aW5nTG9jYWxlIiwidmFsdWVPZiIsImZhbGxiYWNrcyIsImZhbGxiYWNrTG9jYWxlIiwibG9nIiwiZmFsbGJhY2tMb2NhbGVzIiwibG9jSW5kZXgiLCJzb3VyY2VNYXAiLCJwYXJzZSIsInBhcmFtZXRlcnMiLCJsYW5ndWFnZSIsInBhcmFtMSIsImNhbGwiLCJhcmd1bWVudHMiLCJtZXNzYWdlS2V5IiwibEJyYWNrZXQiLCJyQnJhY2tldCIsIm5hdmlnYXRvciIsInVzZXJMYW5ndWFnZSIsInN0ciIsImVtaXR0ZXIiLCJkZWJ1ZyIsImNvbnNvbGUiLCJhcHBseSIsIk1lc3NhZ2VTdG9yZSIsIm1lc3NhZ2VzIiwic291cmNlcyIsImRlZmVycmVkIiwiZGVmZXJyZWRzIiwianNvbk1lc3NhZ2VMb2FkZXIiLCJsb2NhbGl6YXRpb24iLCJwcm9taXNlIiwiRGVmZXJyZWQiLCJyZXNvbHZlIiwid2hlbiIsImdldEpTT04iLCJqcXhociIsInNldHRpbmdzIiwiZXhjZXB0aW9uIiwiYWIiLCJhY2UiLCJhbG4iLCJhbHMiLCJhbiIsImFucCIsImFybiIsImFyeiIsImF2IiwiYXkiLCJiYSIsImJhciIsImJjYyIsImJoIiwiYmpuIiwiYm0iLCJicHkiLCJicWkiLCJidWciLCJjZSIsImNyaCIsImNzYiIsImN2IiwiZHNiIiwiZHRwIiwiZWdsIiwiZW1sIiwiZmYiLCJmaXQiLCJmcmMiLCJmcnAiLCJmcnIiLCJmdXIiLCJnYWciLCJnYW4iLCJnbCIsImdsayIsImduIiwiZ3N3IiwiaGlmIiwiaHNiIiwiaHQiLCJpaSIsImluaCIsIml1IiwianV0IiwianYiLCJrYWEiLCJrYmQiLCJraHciLCJraXUiLCJrayIsImtsIiwia29pIiwia3JjIiwia3MiLCJrc2giLCJrdSIsImt2IiwibGFkIiwibGIiLCJsYmUiLCJsZXoiLCJsaSIsImxpaiIsImxpdiIsImxtbyIsImxuIiwibHRnIiwibHp6IiwibWFpIiwibWciLCJtaHIiLCJtbyIsIm1yaiIsIm13bCIsIm15diIsIm16biIsIm5haCIsIm5hcCIsIm5kcyIsIm5vIiwib3MiLCJwY2QiLCJwZGMiLCJwZHQiLCJwZmwiLCJwbXMiLCJwdCIsInF1IiwicXVnIiwicmduIiwicm15IiwicnVlIiwicnVxIiwic2EiLCJzYWgiLCJzY24iLCJzZyIsInNncyIsInNsaSIsInNyIiwic3JuIiwic3RxIiwic3UiLCJzemwiLCJ0Y3kiLCJ0ZyIsInR0IiwidHkiLCJ1ZG0iLCJ1ZyIsInVrIiwidmVjIiwidmVwIiwidmxzIiwidm1mIiwidm90IiwidnJvIiwid2EiLCJ3byIsInd1dSIsInhhbCIsInhtZiIsInlpIiwiemEiLCJ6ZWEiLCJ6aCIsIk1lc3NhZ2VQYXJzZXIiLCJzaW1wbGVQYXJzZSIsInJlcGxhY2VtZW50cyIsImVtaXQiLCJhc3QiLCJwaXBlIiwiY29sb24iLCJiYWNrc2xhc2giLCJhbnlDaGFyYWN0ZXIiLCJkb2xsYXIiLCJkaWdpdHMiLCJyZWd1bGFyTGl0ZXJhbCIsInJlZ3VsYXJMaXRlcmFsV2l0aG91dEJhciIsInJlZ3VsYXJMaXRlcmFsV2l0aG91dFNwYWNlIiwiZXNjYXBlZE9yTGl0ZXJhbFdpdGhvdXRCYXIiLCJlc2NhcGVkT3JSZWd1bGFyTGl0ZXJhbCIsInRlbXBsYXRlQ29udGVudHMiLCJ0ZW1wbGF0ZU5hbWUiLCJvcGVuVGVtcGxhdGUiLCJjbG9zZVRlbXBsYXRlIiwiZXhwcmVzc2lvbiIsInBhcmFtRXhwcmVzc2lvbiIsInJlc3VsdCIsImNob2ljZSIsInBhcnNlclN5bnRheCIsInNlcXVlbmNlIiwicmVzIiwib3JpZ2luYWxQb3MiLCJuT3JNb3JlIiwibiIsInAiLCJwYXJzZWQiLCJtYWtlU3RyaW5nUGFyc2VyIiwibGVuIiwibWFrZVJlZ2V4UGFyc2VyIiwidHJhbnNmb3JtIiwibGl0ZXJhbFdpdGhvdXRCYXIiLCJsaXRlcmFsIiwiZXNjYXBlZExpdGVyYWwiLCJyZXBsYWNlbWVudCIsInRlbXBsYXRlUGFyYW0iLCJleHByIiwidGVtcGxhdGVXaXRoUmVwbGFjZW1lbnQiLCJ0ZW1wbGF0ZVdpdGhPdXRSZXBsYWNlbWVudCIsInRlbXBsYXRlIiwiRXJyb3IiLCJNZXNzYWdlUGFyc2VyRW1pdHRlciIsIm5vZGUiLCJyZXQiLCJzdWJub2RlcyIsIm9wZXJhdGlvbiIsIm1lc3NhZ2VQYXJzZXJFbWl0dGVyIiwibm9kZXMiLCJwbHVyYWwiLCJjb252ZXJ0TnVtYmVyIiwiZm9ybXMiLCJjb252ZXJ0UGx1cmFsIiwiZ2VuZGVyIiwiZ3JhbW1hciIsImZvcm0iLCJ3b3JkIiwiY29udmVydEdyYW1tYXIiLCJwbHVyYWxSdWxlcyIsInBsdXJhbEZvcm1JbmRleCIsImV4cGxpY2l0UGx1cmFsUGF0dGVybiIsImZvcm1Db3VudCIsInRlc3QiLCJnZXRQbHVyYWxGb3JtIiwibnVtYmVyIiwicGx1cmFsRm9ybXMiLCJwbHVyYWxSdWxlUGFyc2VyIiwibnVtIiwiaW50ZWdlciIsInRtcCIsInRyYW5zZm9ybVRhYmxlIiwibnVtYmVyU3RyaW5nIiwiY29udmVydGVkTnVtYmVyIiwiZGlnaXRUcmFuc2Zvcm1UYWJsZSIsInRhYmxlcyIsImFyIiwiZmEiLCJtbCIsImtuIiwibG8iLCJvciIsImtoIiwicGEiLCJndSIsImhpIiwibXkiLCJ0YSIsInRlIiwidGgiLCJibyIsInJvb3QiLCJmYWN0b3J5IiwiZGVmaW5lIiwiYW1kIiwiZXhwb3J0cyIsIm1vZHVsZSIsInJ1bGUiLCJvcGVyYW5kIiwicmVsYXRpb24iLCJ3aGl0ZXNwYWNlIiwiX25fIiwiX2lfIiwiX2ZfIiwiX3RfIiwiX3ZfIiwiX3dfIiwiX2lzXyIsIl9pc25vdF8iLCJfaXNub3Rfc2lnbl8iLCJfZXF1YWxfIiwiX21vZF8iLCJfcGVyY2VudF8iLCJfbm90XyIsIl9pbl8iLCJfd2l0aGluXyIsIl9yYW5nZV8iLCJfY29tbWFfIiwiX29yXyIsIl9hbmRfIiwicGFyc2VyUmVzIiwiZiIsInQiLCJ2IiwidyIsIm1vZCIsIm5vdCIsImlzbm90Iiwibm90X2luIiwicmFuZ2VfbGlzdCIsInJhbmdlTGlzdCIsInJhbmdlIiwicmFuZ2VUYWlsIiwicmVzdWx0TGlzdCIsIl9pbiIsIndpdGhpbiIsImFuZCIsImFuZFRhaWwiLCJvclRhaWwiLCJjb25kaXRpb24iXSwic291cmNlUm9vdCI6IiJ9