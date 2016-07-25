var stylepath = '//upload.wikimedia.org/wikipedia';
var wgContentLanguage = 'en';



/*
 * Table sorting script  by Joost de Valk, check it out at http://www.joostdevalk.nl/code/sortable-table/.
 * Based on a script from http://www.kryogenix.org/code/browser/sorttable/.
 * Distributed under the MIT license: http://www.kryogenix.org/code/browser/licence.html .
 *
 * Copyright (c) 1997-2006 Stuart Langridge, Joost de Valk.
 *
 * @todo don't break on colspans/rowspans (bug 8028)
 * @todo language-specific digit grouping/decimals (bug 8063)
 * @todo support all accepted date formats (bug 8226)
 * 
 * Modififed (c) 2014 Hedonil
 */

var ts_image_path = stylepath+"/commons/7/73/";
var ts_image_up = "Sort_both.svg";
var ts_image_down = "Sort_both.svg";
var ts_image_none = "Sort_both.svg";
var ts_europeandate = wgContentLanguage != "en"; // The non-American-inclined can change to "true"
var ts_alternate_row_colors = true;
var SORT_COLUMN_INDEX;

function sortables_init() {
        var idnum = 0;
        // Find all tables with class sortable and make them sortable
        var tables = getElementsByClassName(document, "table", "sortable");
        for (var ti = 0; ti < tables.length ; ti++) {
                if (!tables[ti].id) {
                        tables[ti].setAttribute('id','sortable_table_id_'+idnum);
                        ++idnum;
                }
                ts_makeSortable(tables[ti]);
        }
}

function ts_makeSortable(table) {
        var firstRow;
        if (table.rows && table.rows.length > 0) {
                if (table.tHead && table.tHead.rows.length > 0) {
                        firstRow = table.tHead.rows[table.tHead.rows.length-1];
                } else {
                        firstRow = table.rows[0];
                }
        }
        if (!firstRow) return;

        // We have a first row: assume it's the header, and make its contents clickable links
        for (var i = 0; i < firstRow.cells.length; i++) {
                var cell = firstRow.cells[i];
                if (table.className.indexOf("leantable") != -1 ){
                	var oldCont = cell.innerHTML;
                	cell.innerHTML = '<a href="#" class="sortheader" onclick="ts_resortTable(this);return false;"><span class="sortarrow" >'+oldCont+' ↕</span></a>';
                }
                else if ((" "+cell.className+" ").indexOf(" unsortable ") == -1) {
                        cell.innerHTML += '&nbsp;&nbsp;<a href="#" class="sortheader" onclick="ts_resortTable(this);return false;"><span class="sortarrow"><img src="'+ ts_image_path + ts_image_none + '" alt="&darr;"/></span></a>';
                }
        }
        if (ts_alternate_row_colors) {
                ts_alternate(table);
        }
}

function ts_getInnerText(el) {
        if (typeof el == "string") return el;
        if (typeof el == "undefined") { return el };
        //if (el.innerText) return el.innerText;        // Not needed but it is faster
        var str = "";

        var cs = el.childNodes;
        var l = cs.length;
        for (var i = 0; i < l; i++) {
                switch (cs[i].nodeType) {
                        case 1: //ELEMENT_NODE
                                str += ts_getInnerText(cs[i]);
                                break;
                        case 3: //TEXT_NODE
                                str += cs[i].nodeValue;
                                break;
                }
        }
        return str;
}

function ts_resortTable(lnk) {
        // get the span
        var span = lnk.getElementsByTagName('span')[0];

        var td = lnk.parentNode;
        var tr = td.parentNode;
        var column = td.cellIndex;

        var table = tr.parentNode;
        while (table && !(table.tagName && table.tagName.toLowerCase() == 'table'))
                table = table.parentNode;
        if (!table) return;

        // Work out a type for the column
        if (table.rows.length <= 1) return;

        // Skip the first row if that's where the headings are
        var rowStart = (table.tHead && table.tHead.rows.length > 0 ? 0 : 1);

        var itm = "";
        for (var i = rowStart; i < table.rows.length; i++) {
                if (table.rows[i].cells.length > column) {
                        itm = ts_getInnerText(table.rows[i].cells[column]);
                        itm = itm.replace(/^[\s\xa0]+/, "").replace(/[\s\xa0]+$/, "");
                        if (itm != "") break;
                }
        }

        sortfn = ts_sort_caseinsensitive;
        if (itm.match(/^\d\d[\/. -][a-zA-Z]{3}[\/. -]\d\d\d\d$/))
                sortfn = ts_sort_date;
        if (itm.match(/^\d\d[\/.-]\d\d[\/.-]\d\d\d\d$/))
                sortfn = ts_sort_date;
        if (itm.match(/^\d\d[\/.-]\d\d[\/.-]\d\d$/))
                sortfn = ts_sort_date;
        if (itm.match(/^[\u00a3$\u20ac]/)) // pound dollar euro
                sortfn = ts_sort_currency;
        if (itm.match(/^[\d.,]+\%?$/))
                sortfn = ts_sort_numeric;

        var reverse = (span.getAttribute("sortdir") == 'down');

        var newRows = new Array();
        for (var j = rowStart; j < table.rows.length; j++) {
                var row = table.rows[j];
                var keyText = ts_getInnerText(row.cells[column]);
                var oldIndex = (reverse ? -j : j);

                newRows[newRows.length] = new Array(row, keyText, oldIndex);
        }

        newRows.sort(sortfn);

        var arrowHTML;
        if (reverse) {
                        arrowHTML = '<img src="'+ ts_image_path + ts_image_down + '" alt="&darr;"/>';
                        newRows.reverse();
                        span.setAttribute('sortdir','up');
        } else {
                        arrowHTML = '<img src="'+ ts_image_path + ts_image_up + '" alt="&uarr;"/>';
                        span.setAttribute('sortdir','down');
        }

        // We appendChild rows that already exist to the tbody, so it moves them rather than creating new ones
        // don't do sortbottom rows
        for (var i = 0; i < newRows.length; i++) {
                if ((" "+newRows[i][0].className+" ").indexOf(" sortbottom ") == -1)
                        table.tBodies[0].appendChild(newRows[i][0]);
        }
        // do sortbottom rows only
        for (var i = 0; i < newRows.length; i++) {
                if ((" "+newRows[i][0].className+" ").indexOf(" sortbottom ") != -1)
                        table.tBodies[0].appendChild(newRows[i][0]);
        }

        // Delete any other arrows there may be showing --only if not "leantbable
        if (table.className.indexOf("leantable") == -1 ){
	        var spans = getElementsByClassName(tr, "span", "sortarrow");
	        for (var i = 0; i < spans.length; i++) {
	                spans[i].innerHTML = '<img src="'+ ts_image_path + ts_image_none + '" alt="&darr;"/>';
	        }
	        span.innerHTML = arrowHTML;
        }
        

        ts_alternate(table);            
}

function ts_dateToSortKey(date) {       
        // y2k notes: two digit years less than 50 are treated as 20XX, greater than 50 are treated as 19XX
        if (date.length == 11) {
                switch (date.substr(3,3).toLowerCase()) {
                        case "jan": var month = "01"; break;
                        case "feb": var month = "02"; break;
                        case "mar": var month = "03"; break;
                        case "apr": var month = "04"; break;
                        case "may": var month = "05"; break;
                        case "jun": var month = "06"; break;
                        case "jul": var month = "07"; break;
                        case "aug": var month = "08"; break;
                        case "sep": var month = "09"; break;
                        case "oct": var month = "10"; break;
                        case "nov": var month = "11"; break;
                        case "dec": var month = "12"; break;
                        // default: var month = "00";
                }
                return date.substr(7,4)+month+date.substr(0,2);
        } else if (date.length == 10) {
                if (ts_europeandate == false) {
                        return date.substr(6,4)+date.substr(0,2)+date.substr(3,2);
                } else {
                        return date.substr(6,4)+date.substr(3,2)+date.substr(0,2);
                }
        } else if (date.length == 8) {
                yr = date.substr(6,2);
                if (parseInt(yr) < 50) { 
                        yr = '20'+yr; 
                } else { 
                        yr = '19'+yr; 
                }
                if (ts_europeandate == true) {
                        return yr+date.substr(3,2)+date.substr(0,2);
                } else {
                        return yr+date.substr(0,2)+date.substr(3,2);
                }
        }
        return "00000000";
}

function ts_parseFloat(num) {
        if (!num) return 0;
        num = parseFloat(num.replace(/[,.]/g, '' ));
        return (isNaN(num) ? 0 : num);
}

function ts_sort_date(a,b) {
        var aa = ts_dateToSortKey(a[1]);
        var bb = ts_dateToSortKey(b[1]);
        return (aa < bb ? -1 : aa > bb ? 1 : a[2] - b[2]);
}

function ts_sort_currency(a,b) {
        var aa = ts_parseFloat(a[1].replace(/[^0-9.]/g,''));
        var bb = ts_parseFloat(b[1].replace(/[^0-9.]/g,''));
        return (aa != bb ? aa - bb : a[2] - b[2]);
}

function ts_sort_numeric(a,b) {
        var aa = ts_parseFloat(a[1]);
        var bb = ts_parseFloat(b[1]);
        return (aa != bb ? aa - bb : a[2] - b[2]);
}

function ts_sort_caseinsensitive(a,b) {
        var aa = a[1].toLowerCase();
        var bb = b[1].toLowerCase();
        return (aa < bb ? -1 : aa > bb ? 1 : a[2] - b[2]);
}

function ts_sort_default(a,b) {
        return (a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : a[2] - b[2]);
}

function ts_alternate(table) {
        // Take object table and get all it's tbodies.
        var tableBodies = table.getElementsByTagName("tbody");
        // Loop through these tbodies
        for (var i = 0; i < tableBodies.length; i++) {
                // Take the tbody, and get all it's rows
                var tableRows = tableBodies[i].getElementsByTagName("tr");
                // Loop through these rows
                // Start at 1 because we want to leave the heading row untouched
                for (var j = 0; j < tableRows.length; j++) {
                        // Check if j is even, and apply classes for both possible results
                        var oldClasses = tableRows[j].className.split(" ");
                        var newClassName = "";
                        for (var k = 0; k < oldClasses.length; k++) {
                                if (oldClasses[k] != "" && oldClasses[k] != "even" && oldClasses[k] != "odd")
                                        newClassName += oldClasses[k] + " ";
                        }
                        tableRows[j].className = newClassName + (j % 2 == 0 ? "even" : "odd");
                }
        }
}

/*
 * End of table sorting code
 */
 


/*
        Written by Jonathan Snook, http://www.snook.ca/jonathan
        Add-ons by Robert Nyman, http://www.robertnyman.com
        Author says "The credit comment is all it takes, no license. Go crazy with it!:-)"
        From http://www.robertnyman.com/2005/11/07/the-ultimate-getelementsbyclassname/
*/
function getElementsByClassName(oElm, strTagName, oClassNames){
        var arrElements = (strTagName == "*" && oElm.all)? oElm.all : oElm.getElementsByTagName(strTagName);
        var arrReturnElements = new Array();
        var arrRegExpClassNames = new Array();
        if(typeof oClassNames == "object"){
                for(var i=0; i<oClassNames.length; i++){
                        arrRegExpClassNames.push(new RegExp("(^|\\s)" + oClassNames[i].replace(/\-/g, "\\-") + "(\\s|$)"));
                }
        }
        else{
                arrRegExpClassNames.push(new RegExp("(^|\\s)" + oClassNames.replace(/\-/g, "\\-") + "(\\s|$)"));
        }
        var oElement;
        var bMatchesAll;
        for(var j=0; j<arrElements.length; j++){
                oElement = arrElements[j];
                bMatchesAll = true;
                for(var k=0; k<arrRegExpClassNames.length; k++){
                        if(!arrRegExpClassNames[k].test(oElement.className)){
                                bMatchesAll = false;
                                break;
                        }
                }
                if(bMatchesAll){
                        arrReturnElements.push(oElement);
                }
        }
        return (arrReturnElements)
}


//Run sortables init function
sortables_init();
//ts_resortTable(this);
//alert(document.getElementById('wikitable-autosort'));

