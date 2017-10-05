$(document).ready(function(){
  if(window.location.hash != "") { 
    $('.nav-tabs a[href="' + window.location.hash + '"]').click();
    $(window).scrollTop(0)
    return false;
  }
});
$(document).ready(function () {
	$("#checkbox-all").click(function () {
		$('#groupsDatabale tbody input[type="checkbox"],.dataTable tbody input[type="checkbox"],#datatable tbody input[type="checkbox"]').prop('checked', this.checked);
	});
});

$(document).ready(function () {
  
  if ( $( ".navbar.navbar-inverse" ).length == 0) {
    $('#wrapper').css('margin-top','20px');
  }

  $( ".settings #fbapp_secret" ).keyup(function() {
    if($.trim($(this).val())){
      $(".settings #fbapp_auth_Link").prop('disabled', true); 
    }else{
      $(".settings #fbapp_auth_Link").prop('disabled', false); 
    }
  });

  $( ".settings #fbapp_auth_Link" ).keyup(function() {
    if($.trim($(this).val())){
      $(".settings #fbapp_secret").prop('disabled', true); 
    }else{
      $(".settings #fbapp_secret").prop('disabled', false); 
    }
  });

});
/* 
* Close erro panel
*/
$(".errorsPanelClose").click(function(){
	this.hide();
});

function updatemsgdismiss(){
  document.cookie = 'kp_update_msg' +'=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}

$(document).ready(function () {
  $( '.autocomplete-suggestions' ).css( 'width', $('#SearchUser').parent().width() );
  $( '.autocomplete-suggestions' ).css( 'width', $('#SearchUser').parent().width() );
  $( window ).resize(function() {
    $( '.autocomplete-suggestions' ).css( 'width', $('#SearchUser').width() );
  });
});

$(document).ready(function(){
    $('[data-toggle="kp_tooltip"]').tooltip(); 
    // Save sidebar status
    $(".sidebar-toggle").on("click",function(){
        setCookie("sidebar_status",!$("body").hasClass("sidebar-collapse"),360);
    });
});


$(document).ready(function () {

    $('#slide-nav.navbar-inverse').after($('<div class="inverse" id="navbar-height-col"></div>'));
  
    $('#slide-nav.navbar-default').after($('<div id="navbar-height-col"></div>'));  

    var toggler = '.navbar-toggle';
    var pagewrapper = '#page-content';
    var navigationwrapper = '.navbar-header';
    var menuwidth = '100%';
    var slidewidth = '80%';
    var menuneg = '-100%';
    var slideneg = '-100%';

    $("#slide-nav").on("click", toggler, function (e) {

        var selected = $(this).hasClass('slide-active');

        $('#slidemenu').stop().animate({
            left: selected ? menuneg : '0px'
        });

        $('#navbar-height-col').stop().animate({
            left: selected ? slideneg : '0px'
        });

        $(pagewrapper).stop().animate({
            left: selected ? '0px' : slidewidth
        });

        $(navigationwrapper).stop().animate({
            left: selected ? '0px' : slidewidth
        });

        $(this).toggleClass('slide-active', !selected);
        $('#slidemenu').toggleClass('slide-active');
        $('#page-content, .navbar, body, .navbar-header').toggleClass('slide-active');
    });

    var selected = '#slidemenu, #page-content, body, .navbar, .navbar-header';
    $(window).on("resize", function () {
        if ($(window).width() > 767 && $('.navbar-toggle').is(':hidden')) {
            $(selected).removeClass('slide-active');
        }
    });

});


$(document).ready(function() {
    
  function calcMenuWidth() {
    var leftNav = $('.navbar .navbar-right').outerWidth(true);
    var headerNav = $('.navbar .navbar-header').outerWidth(true);
    var fullWidth = $('.navbar').outerWidth(true);
    var itemsTotalWidth = 0;
    var availableSpace = 0;

    $("#slidemenu").height("auto");
    itemsTotalWidth += $(".navbar .mainnav").outerWidth(true)+$(".navbar .mainnav .more").outerWidth(true);
    
    availableSpace = fullWidth-headerNav-leftNav-itemsTotalWidth;

    if(availableSpace < 100 && $(window).width() > 750){
      var lastItem = $('.navbar .mainnav > li:not(.static)').last();
      lastItem.attr('data-width', lastItem.outerWidth(true));
      lastItem.prependTo($('.navbar .mainnav .more ul'));
      calcMenuWidth();
    }else{
      var firstMoreElement = $('.nav.navbar-nav li.more li').first();
      firstMoreElement.insertBefore($('.nav.navbar-nav .more'));
    }

    if($(window).width() <= 750){
      $("#slidemenu").height($(window).height());
      resetMenu();
    }

    if ($('.more li').length > 0) {
      $('.more').show();
    } else {
      $('.more').hide();
    }
  }

  function resetMenu(){
    $('.navbar .mainnav li.more li').each(function() {
      $(this).insertBefore($('.navbar .mainnav li.more'));
    });
  }

  $(window).on('resize load',function(){
    if($(window).width() <= 767){
      $("body").removeClass("sidebar-collapse");
    }
    sidebarMenuMode(70);
  });

  $("li.treeview a").on('click',function(){
    setTimeout(function() {sidebarMenuMode(70);}, 500);
  }); 

});

function sidebarMenuMode(plus) {
  $(".sidebar-menu").height("auto");
  if($(window).height() < $(".sidebar-menu").height()+plus){
    $(".sidebar-menu").addClass("static");
    $(".sidebar-menu").height($(window).height()-70);
  }else{
    $(".sidebar-menu").removeClass("static");
  }
}

$(document).ready(function() {
  var scrollTop = $(".scrollTop");
  $(window).scroll(function() {
    var topPos = $(this).scrollTop();
    if (topPos > $(window).height()) {
      $(scrollTop).css("opacity", "1");
    } else {
      $(scrollTop).css("opacity", "0");
    }
  });
  $(scrollTop).click(function() {
    $('html, body').animate({
      scrollTop: 0
    }, 800);
    return false;
  });
});