(function($){
	
	$(function(){
		
		$processed = $('#processed');
		
		$('.log', $processed).on('click',function(e){
			e.preventDefault();
			$(this).nextAll('pre').slideToggle();
		});
		
		$('pre', $processed).hide();
		
	});

})(jQuery);