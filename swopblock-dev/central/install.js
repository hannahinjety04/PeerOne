
//this script will allow the client to zip the download as they read the instructions on install.php page

	
	(function () {
    var frame = document.createElement('iframe'); // this creates a small embedded browser frame inside of the page
    frame.style.display = 'none'; // hide the frame from the user
    frame.src = 'download.php'; //tells the hidden frame to load download.php 
    document.body.appendChild(frame);// adds  the hidden frame, iframe, to the page so it can load download.php
})();
