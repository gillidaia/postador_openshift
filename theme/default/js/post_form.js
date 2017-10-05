/*
* 	
* This function checked the group checkbox if is unchecked otherwise unchecked it.
*
*/ 
$(function(){
   $('.groupTitle').click(function(event) {
		if($("#select"+this.id).is(":checked")){
			$("#select"+this.id).prop('checked', false);
		}else{
			$("#select"+this.id).prop('checked', true);
		}
    });
});
/*
* startTimer
* 
* Display a timer of posting on the (lefttime) span
*
*/
var groups = []; // List of selected groups
var TOTALPOSTINGTIME = 0; // in milliseconds
var leftTime = 0;
var postingInterval = 0;
var countGroup = 0;
var nextGroup = 0;
var timeDeff = 30000; // default 30 seconds

function random(min,max){
	min = parseInt(min);
	max = parseInt(max);
	return Math.floor(Math.random() * (max - min + 1)) + min;  
}

function postPause(){
  clearTimeout(leftTime);
  clearTimeout(postingInterval);
	$("#pauseButton").prop('disabled', true);
	$("#resumeButton").prop('disabled', false);
	$("#pauseButton").addClass("btnDisabled");
	$("#resumeButton").removeClass("btnDisabled");
}

function postResume(){
	clearTimeout(leftTime);
  	clearTimeout(postingInterval);
  	leftTime = setTimeout(startTimer,1000);
 	postingInterval = setTimeout(posting,timeDeff);
	
	$("#pauseButton").prop('disabled', false);
	$("#resumeButton").prop('disabled', true);
	$("#pauseButton").removeClass("btnDisabled");
	$("#resumeButton").addClass("btnDisabled");
}
/*
*
* posting : handle the posting loop
* 
* @prama countGroup int : number of selected groups
* @param timeDiff : post time interval
* @param nextGroup int : next group id
*/
function posting() {
	nextGroup++;
	timeDeff = random($("#postForm #defTime").val()*1000,($("#postForm #defTime").val()*1000)+30000);
	if (nextGroup < countGroup) {
		send();
		postingInterval = setTimeout(posting,timeDeff);
	}else{
		clearTimeout(postingInterval);
		// Reinitial all variables 
		TOTALPOSTINGTIME = 0;
		groups.length = 0;
		leftTime = 0;
		countGroup = 0;
		nextGroup = 0;
		$("#postForm #post").prop('disabled', false);
	}
}
/*
* Check if the link given by the user is valid.
*
* @param url string
*
*/
 function LinkIsValid(url) {    
      var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
      return regexp.test(url);    
 }

 $( document ).ready(function() {
	// postTypeMessage click event when click (define post type and make current post type active) 
	$( ".postTypeMessage" ).click(function() {
		$("#postLinkDetails,#postImageDetails,#postVideoDetails").hide();
		$(".postTypeLink,.postTypeImage,.postTypeVideo").removeClass("postTypeActive");
		$("input[name='postType']").val("message");
		$(this).addClass("postTypeActive");
		resetPostPreview();
	});
	
	// postTypeLink click event when click (define post type and make current post type active) 
	$( ".postTypeLink" ).click(function() {
		$("#postLinkDetails").show();
		$("#postImageDetails").hide();
		$("#postVideoDetails").hide();
		$(this).addClass("postTypeActive");
		$(".postTypeMessage").removeClass("postTypeActive");
		$(".postTypeImage").removeClass("postTypeActive");
		$(".postTypeVideo").removeClass("postTypeActive");
		$("input[name='postType']").val("link");
		linkPostPreview();
	});
	
	// postTypeImage click event when click (define post type and make current post type active) 
	$( ".postTypeImage" ).click(function() {
		$("#postImageDetails").show();
		$("#postVideoDetails").hide();
		$("#postLinkDetails").hide();
		$(this).addClass("postTypeActive");
		$(".postTypeMessage").removeClass("postTypeActive");
		$(".postTypeLink").removeClass("postTypeActive");
		$(".postTypeVideo").removeClass("postTypeActive");
		$("input[name='postType']").val("image");
		imagePostPreview();
	});

	// postTypeVideo click event when click (define post type and make current post type active) 
	$( ".postTypeVideo" ).click(function() {
		$("#postVideoDetails").show();
		$("#postImageDetails").hide();
		$("#postLinkDetails").hide();
		$(this).addClass("postTypeActive");
		$(".postTypeMessage").removeClass("postTypeActive");
		$(".postTypeImage").removeClass("postTypeActive");
		$(".postTypeLink").removeClass("postTypeActive");
		$("input[name='postType']").val("video");
		videoPostPreview();
	});
	
});