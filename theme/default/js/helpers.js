function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i <ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length,c.length);
        }
    }
    return "";
}

function setCookie(name,value,days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

function alertBox(message,type,errorHolder,showIcon,close){
    var icons = {}; 
    icons['success'] = 'check-circle';
    icons['info'] = 'info-circle';
    icons['warning'] = 'exclamation-circle';
    icons['danger'] = 'exclamation-circle';
    icons['primary'] = 'info-circle';
                
    var html = "<div class='alert alert-"+type+"' role='alert'>";
    if(close) html += "<a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a>";
    if(showIcon) html += "<span class='fa fa-"+icons[type]+"-sign' aria-hidden='true'></span>&nbsp;";
            html += message+"</div>";

    $( document ).ready(function() {
        if(errorHolder){
            $(errorHolder).hide();
            $(errorHolder).html(html);
            $(errorHolder).fadeIn(300);
        }else{
            $(".alerts").hide();
            $(".alerts").html(html);
            $(".alerts").fadeIn(300);
            setTimeout(function(){$(".alerts").fadeOut();}, 5000);
        }
    });
}

function kp_preloader(status,placeHolder){
    preloader = new $.materialPreloader({placeHolder: placeHolder});
    if(status == "on"){
        preloader.on(placeHolder);
    }else{
        preloader.off(placeHolder);
    }
}

$(document).ready(function () {
    $("#checkbox-all").click(function () {
    $('.table tbody input[type="checkbox"]').prop('checked', this.checked);
    });    
});

var exportToCSV = function(content, fileName, mimeType) {
  var a = document.createElement('a');
  mimeType = mimeType || 'application/octet-stream';

  if (navigator.msSaveBlob) { // IE10
    navigator.msSaveBlob(new Blob([content], {
      type: mimeType
    }), fileName);
  } else if (URL && 'download' in a) {
    a.href = URL.createObjectURL(new Blob([content], {
      type: mimeType
    }));
    a.setAttribute('download', fileName);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  } else {
    location.href = 'data:application/octet-stream,' + encodeURIComponent(content);
  }
}

var tagsToReplace = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;'
};

function kpEscape(tag) {
    return tagsToReplace[tag] || tag;
}

function safe_tags_replace(str) {
    return str.replace(/[&<>]/g, kpEscape);
}

function csvToArray (csv) {
    rows  = csv.split("\n");
    return rows.map(function (row) {
        return row.split(",");
    });
};