/*
 * Spintax for post preview
 * 
 */
var SPINTAX_PATTERN = /\{[^\r\n\}]*\}/;
var spin = function (spun) {
	var match;
	while (match = spun.match(SPINTAX_PATTERN)) {
		match = match[0];
		var candidates = match.substring(1, match.length - 1).split("|");
		spun = spun.replace(match, candidates[Math.floor(Math.random() * candidates.length)])
	}
	return spun;
}
	
function defaultPreviewImg(imgHolder) {
	imgHolder.src = "theme/default/images/defaultPreviewImg.png";
}

/*
 * Extract root domain name from string
 * @param url string : the url
 */
function extractDomain(url) {
    var domain;
    //find & remove protocol (http, ftp, etc.) and get domain
    if (url.indexOf("://") > -1) {
        domain = url.split('/')[2];
    }
    else {
        domain = url.split('/')[0];
    }

    //find & remove port number
    domain = domain.split(':')[0];
	
    return domain;
}
 /*
 *
 * Testing the url given is a youtube video url
 * @param url string
 *
 */
 function IsVideo(url) {    
      var regexp = /^(.*\.(?!(3g2|3gp|3gpp|asf|avi|dat|divx|dv|f4v|flv|m2ts|m4v|mkv|mod|mov|mp4|mpe|mpeg|mpeg4|mpg|mts|nsv|ogm|ogv|qt|tod|ts|vob|wmv)$))?[^.]*$/i;
	  return !regexp.test(url);	  
 }

  /*
 *
 * Testing the url given is a video url
 * @param url string
 *
 */
 function IsYoutubeVideo(url) {    
      var regexp = /^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/;
	  return regexp.test(url);	  
 }
 /*
*
* Default post preview reset the preview post to the default preview.
*
*/
 function defaultPreview(){
	 if(!$("input[name='picture']").val()){
		$(".postPreview .picture").html('src',"");
	 }
	 if(!$("input[name='name']").val()){
		$(".postPreview .name").html("<span class='defaultName'></span>");
	 }
	 if(!$("input[name='description']").val()){
		 $(".postPreview .description").html("<span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span>");
	}
	if(!$("input[name='caption']").val()){
		$(".postPreview .caption").html("<span class='defaultCaption'></span>");
	}
 }
 
$( document ).ready(function() {

	$("body").on('DOMSubtreeModified', ".emojionearea-editor", function() {
		$( ".emojionearea-editor" ).trigger('keydown');
	});

	// Preview instant update (message)
	$('#message,.emojionearea-editor').bind('input propertychange change', function() {
		if($.trim($(this).val()) != ""){
			var text = $(this).val();
			$(".postPreview .message").html(spin(text.replace(/(?:\r\n|\r|\n)/g, '<br />')));
		}else{
			$(".postPreview .message").html("<span class='defaultMessage' style='width: 60%'></span><span class='defaultMessage' style='width: 80%'></span><span class='defaultMessage' style='width: 40%'></span>");
		}
	});

	$('.emojionearea-editor').bind('input propertychange change', function() {
		if($.trim($(this).val()) != ""){
			var text = $(this).val();
			$(".postPreview .message").html(spin(text.replace(/(?:\r\n|\r|\n)/g, '<br />')));
		}else{
			$(".postPreview .message").html("<span class='defaultMessage' style='width: 60%'></span><span class='defaultMessage' style='width: 80%'></span><span class='defaultMessage' style='width: 40%'></span>");
		}
	});

	$('#link').bind('input propertychange', function() {
		$(".linkError").html("");
		var link = spin($(this).val());
		$(".alerts").hide();
		if($.trim(link) != ""){
			if(LinkIsValid(link)){
				$(".previewPost .previewPostlink").html(link);
				if(IsYoutubeVideo(link)){
					
					var videoID = link.match(/=([^\&\?\/]+)/)[1];
					 
					$(".previewLink").html("<iframe src='https://www.youtube.com/embed/"+videoID+"' width='470px' height='300px' frameborder='0' allowfullscreen='allowfullscreen'></iframe>");
					
					GetSiteDetails(link,function(data) {
						if(data.status == "ok"){
							$(".postPreview .name").html(data.url.title);
							$(".postPreview .description").html(data.url.description);

							if($( "#postForm #name" ).val() == ""){
								$(".postPreview .name").html(data.url.title);
							}
							
							if($( "#postForm #description" ).val() == ""){
								$(".postPreview .description").html(data.url.description);
							}
						}
					});

					if(!$("input[name='caption']").val()){
						$(".postPreview .caption").html("youtube.com");
					}
					
				}else if(IsVideo(link)){
					$(".previewLink").html("<video controls><source src='"+link+"'></source></video>");
				}else{

					$(".postPreview .caption").html(extractDomain(link));
					
					GetSiteDetails(link,function(data) {
						if(data.status == "ok"){

							$(".postPreview .name").html(data.url.title);
							$(".postPreview .description").html(data.url.description);
							$(".previewLink").html("<img src='"+data.url.image+"'>");

							if($( "#postForm #name" ).val() == ""){
								$( "#postForm #name" ).val(data.url.title);
								$(".postPreview .name").html(data.url.title);
							}
							
							if($( "#postForm #picture" ).val() == ""){
								$( "#postForm #picture" ).val(data.url.image);
								$(".previewLink").html("<img src='"+data.url.image+"'>");
							}
							
							if($( "#postForm #description" ).val() == ""){
								$( "#postForm #description" ).val(data.url.description);
								$(".postPreview .description").html(data.url.description);
							}
						}
					});
				 }
			}else{
				alertBox("Invalid link","danger",".linkError",true,true);
				defaultPreview();
			}
		}else{
			defaultPreview();
		}
	});
	
	// Preview instant update (picture)
	$('#picture').bind('input propertychange change', function() {
		var picture = spin($(this).val());
		if($.trim(picture) != ""){
			 $(".postPreview .previewLink").html("<img onerror='defaultPreviewImg(this)' src='"+picture+"' />");

			 $(".postPreview .previewLink img").load(function() {
		        if(parseInt($(this).height()) < 300){
		        	$(this).css({'height':300,'width':$(this).width()});
		        }
		    });

		}else{
			$(".postPreview .previewLink").html("");
		}
	});
	
	
	// Preview instant update (name)
	$('#name').bind('input propertychange change', function() {
		var name = spin($(this).val());
		if($.trim(name) != ""){
			 $(".postPreview .name").html(name);
		}else{
			$(".postPreview .name").html("<span class='defaultName'></span>");
		}
	});
	
	// Preview instant update (caption)
	$('#caption').bind('input propertychange change', function() {
		var caption = spin($(this).val());
		if($.trim(caption) != ""){
			$(".postPreview .caption").html(caption);
		}else{
			$(".postPreview .caption").html("<span class='defaultCaption'></span>");
		}
	});
	
	// Preview instant update (description)
	$('#description').on('input propertychange change', function() {
		var description = spin($(this).val());
		if($.trim(description) != ""){
			$(".postPreview .description").html(description);
		}else{
			$(".postPreview .description").html("<span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span><span class='defaultDescription'></span>");
		}
	});

	$(".multiImages").on('input propertychange change', 'input', function () {
    	imagePostPreview();
    });
	
	$("#postVideoDetails").on('input propertychange change', '#video', function () {
    	videoPostPreview();
    });

	$( "#message" ).trigger('propertychange');

	if( $( "#postType" ).val() == "link" ){
		linkPostPreview();
	}

	if( $( "#postType" ).val() == "image" ){
		$( "#imageURL" ).trigger('propertychange');
		imagePostPreview();
	}

	if( $( "#postType" ).val() == "video" ){
		$( "#video" ).trigger('propertychange');
		$( "#description" ).trigger('propertychange');
		videoPostPreview();
	}
	
});

function imagePostPreview(){
	resetPostPreview();
	var ic = $(".multiImages > .input-group").length;
		
		imageCount = ic > 4 ? 4 : ic;

	var imgblock = "<div class='previewImageType pit"+imageCount+"'>";
		for (var i = 1; i <= imageCount; i++) {
			imgblock += "<div class='image_"+i+"'>";

			if(ic > 4 && i == 4){
				imgblock += "<div class='moreImages'>+"+(ic-4)+"</div>";
			}

			if($.trim($("#imageURL_"+(i-1)).val()) != ""){
				imgblock += "<img src='"+spin($("#imageURL_"+(i-1)).val())+"' />";
			}
			imgblock += "</div>";
		}
	$('.postPreview').append(imgblock);
}

function linkPostPreview(){
	resetPostPreview();
	var linkBlock = "<div class='previewLinkType'>";
		linkBlock += "<div class='previewLink'></div>";
		linkBlock += "<div class='postDetails'>";
		linkBlock += "<p class='name'><span class='defaultName'></span></p>";
		linkBlock += "<p class='description'>";
		linkBlock += "<span class='defaultDescription'></span>";
		linkBlock += "<span class='defaultDescription'></span>";
		linkBlock += "<span class='defaultDescription'></span>";
		linkBlock += "<span class='defaultDescription'></span>";
		linkBlock += "<span class='defaultDescription'></span>";
		linkBlock += "</p>";
		linkBlock += "<p class='caption'><span class='defaultCaption'></span></p>";
		linkBlock += "</div>";
		linkBlock += "</div>";
	$('.postPreview').append(linkBlock);
	$( "#link" ).trigger('propertychange');
		$( "#picture" ).trigger('propertychange');
		$( "#name" ).trigger('propertychange');
		$( "#caption" ).trigger('propertyrtychange');
		$( "#description" ).trigger('propertychange');
}

function videoPostPreview(){
	resetPostPreview();
	var videoBlock = "<div class='previewVideoType'>";
		if($.trim($("#video").val()) != ""){
			videoBlock += "<video controls><source src='"+spin($("#video").val())+"'></source></video>";
		}
		videoBlock += "</div>";
	$('.postPreview').append(videoBlock);
}

function resetPostPreview(){
	$('.postPreview .previewImageType').remove();
	$('.postPreview .previewLinkType').remove();
	$('.postPreview .previewVideoType').remove();
}