(function() {
	document.querySelector("#load-kaf").addEventListener('click', function(e) {
		var lti = document.querySelector("#contentframe");
		lti.src = 'lti_launch.php';
		window.alert('clickety');
	});
	document.querySelector("#load-ce").addEventListener('click', function(e) {
		var lti = document.querySelector("#contentframe");
		lti.src = 'https://urcourses-video.uregina.ca/index.php/kmcng/login';
		window.alert('clicked, fool');
	});
}());