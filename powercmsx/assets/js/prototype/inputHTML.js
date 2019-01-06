function surroundHTML(tag, obj, opt) {
  //
  var format = $('#text_format-select').val();
  var target = document.getElementById(obj);
  var pos = getAreaRange(target);
  var val = target.value;
  var range = val.slice(pos.start, pos.end);
  var beforeNode = val.slice(0, pos.start);
  var afterNode  = val.slice(pos.end);
  var insertNode;
  var tag_e = tag;
  var href = '';
  if ( format == 'markdown' ) {
    var mark = '';
    var mark_end = '';
    if ( tag == 'h1' ) {
      mark = '# ';
    } else if ( tag == 'h2' ) {
      mark = '## ';
    } else if ( tag == 'h3' ) {
      mark = '### ';
    } else if ( tag == 'h4' ) {
      mark = '#### ';
    } else if ( tag == 'strong' ) {
      mark = '**';
      mark_end = '**';
    } else if ( tag == 'em' ) {
      mark = '*';
      mark_end = '*';
    } else if ( tag == 'p' ) {
      mark = "\n";
      mark_end = "\n";
    } else if ( tag == 'blockquote' ) {
      range = range.replace( /\n/g, "\n> " );
      mark = '> ';
    } else if ( tag == 'ul' ) {
      range = range.replace( /\n/g, "\n- " );
      mark = '- ';
    } else if ( tag == 'ol' ) {
      range = range.replace( /\n/g, "\n1. " );
      mark = '1. ';
    } else if ( tag == 'li' ) {
      range = range.replace( /\n/g, "\n- " );
      mark = '- ';
    } else if ( tag == 'br' ) {
      mark_end = "\n";
    } else if ( tag == 'a' ) {
      href = prompt( 'href=' );
      if ( href == null ) {
        return;
      }
      mark = '[';
      if ( href ) {
        mark_end = '](' + href + ')';
      } else {
        mark_end = ']()';
      }
    } else if ( tag == 'file' ) {
      mark = opt;
    }
    if (range || pos.start != pos.end) {
      insertNode = mark + range + mark_end;
    } else if (pos.start == pos.end) {
      insertNode = mark + mark_end;
    }
    target.value = beforeNode + insertNode + afterNode;
  } else {
    if ( tag == 'a' ) {
      href = prompt( 'href=' );
      if ( href == null ) {
        return;
      }
      if ( href ) {
       tag = tag + ' href="' + href + '"';
      }
    }
    if (range || pos.start != pos.end) {
      if ( tag == 'br' ) {
        insertNode = range + '<' + tag + '>';
      } else if ( tag == 'file' ) {
        insertNode = range + opt;
      } else {
        insertNode = '<' + tag + '>' + range + '</' + tag_e + '>';
      }
      target.value = beforeNode + insertNode + afterNode;
    } else if (pos.start == pos.end) {
      if ( tag == 'br' ) {
        insertNode = '<' + tag + '>';
      } else if ( tag == 'file' ) {
        insertNode = opt;
      } else {
        insertNode = '<' + tag + '>' + '</' + tag_e + '>';
      }
      target.value = beforeNode + insertNode + afterNode;
    }
  }
}
function getAreaRange(obj) {
  var pos = new Object();
  if (isIE) {
    obj.focus();
    var range = document.selection.createRange();
    var clone = range.duplicate();
    clone.moveToElementText(obj);
    clone.setEndPoint( 'EndToEnd', range );
    pos.start = clone.text.length - range.text.length;
    pos.end   = clone.text.length - range.text.length + range.text.length;
  } else if(window.getSelection()) {
    pos.start = obj.selectionStart;
    pos.end   = obj.selectionEnd;
  }
  return pos;
}
var isIE = (navigator.appName.toLowerCase().indexOf('internet explorer') + 1 ? 1 : 0 );