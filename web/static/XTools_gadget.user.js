// ==UserScript==
// @name        Wikipedia XTools gadget
// @namespace   http://tools.wmflabs.org/xtools
// @description Provides instant meta information for all Wikimedia wikis.
// @match	*://*.wikipedia.org/*
// @match	*://*.wikimedia.org/*
// @match	*://*.mediawiki.org/*
// @match	*://*.wikidata.org/*
// @match	*://*.wiktionary.org/*
// @match	*://*.wikisource.org/*
// @match	*://*.wikibooks.org/*
// @match	*://*.wikinews.org/*
// @match	*://*.wikiquote.org/*
// @match	*://*.wikiversity.org/*
// @match	*://*.wikivoyage.org/*
// @run-at 	document-end
// @version     1.0
// @grant       none
// ==/UserScript==
var src = '//meta.wikimedia.org/w/index.php?title=User:Hedonil/XTools/XTools.js&action=raw&ctype=text/javascript';
var script = document.createElement('script');
script.type = 'text/javascript';
script.src = src;
(document.head || document.documentElement).appendChild(script);
script.parentNode.removeChild(script);
