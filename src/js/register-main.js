// Register service worker.js
// Based on https://github.com/GoogleChrome/samples/blob/gh-pages/push-messaging-and-notifications/main.js
// first execute config.js

'use strict';

$(document).ready(function () {
    // Use .ready may not be necessary, see http://goo.gl/aAhs  but we'll be
	// conservative and include it.
    
	var cookie_name = 'push_subscriber';
	
	
	function add_eventsXXXXXXXXXXXXXXXXXXXXXXXXX(){
	// need to do this for each button shown in subscription list
		$('#btn-subscribe').on('click', function (e) {
			if (pnds.isPushEnabled) {unsubscribe();} else {subscribe();}
		});
    }

	function send_subscription_to_server(subscription) {
	  // TODO: Send the subscription.subscriptionId and 
	  // subscription.endpoint to your server and save 
	  // it to send a push message at a later date
	  console.log('TODO: Implement send_subscription_to_server()');
	}

	function send_unsubscribe_to_server(subscription) {
	  // TODO: Send the subscription.subscriptionId and 
	  // subscription.endpoint to your server and save 
	  // it to send a push message at a later date
	  console.log('TODO: Implement send_subscription_to_server()');
	}

	function showCurlCommand(subscription) {
	  // The curl command to trigger a push message straight from GCM
	  var subscriptionId = subscription.subscriptionId;
	  var endpoint = subscription.endpoint;
	  var curlCommand = 'curl --header "Authorization: key=' + pnds.API_KEY +
		'" --header Content-Type:"application/json" ' + endpoint + 
		' -d "{\\"registration_ids\\":[\\"' + subscriptionId + '\\"]}"';

	  curlCommandDiv.textContent = curlCommand;
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// unsubscribe
	function unsubscribe() {
	//  var pushButton = document.querySelector('.js-push-button');
	//  pushButton.disabled = true;

		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			// To unsubscribe from push messaging, you need to get the
			// subscription object, which you can call unsubscribe() on.
			serviceWorkerRegistration.pushManager.getSubscription().then(
				function(subscription) {
					// Check we have a subscription to unsubscribe
					if (!subscription) {
						// No subscription object, so reset state
						pnds.isPushEnabled = false;
						add_subscription_enable();
						return;
					}
				
					// We have a subscription, so call unsubscribe on it
					internal_unsubscribe(subscription);
				}
			).catch(function(e) {
				window.Demo.debug.log('Problem from Push Manager.', e);
				post_message('<p>Unsubscribed.</p><small>Issue: Problem with Push Manager</small>');
			});
		});
	}

	function internal_unsubscribe(subscription) {
		subscription.unsubscribe().then(function(successful) {
			unsubscribed(subscription);
			post_message('<p>Unsubscribed.</p>');
		}).catch(function(e) {
			// We failed to unsubscribe, this can lead to
			// an unusual state, so may be best to remove 
			// the subscription id from your data store and 
			// inform the user that you disabled push
			unsubscribed(subscription);
			post_message('<p>Unsubscribed.</p><small>Issue: Problem with unsubscribing</small>');
		});
	}
	
	function unsubscribed(subscription) {
		pnds.isPushEnabled = false;
		send_unsubscribe_to_server(subscription);
		add_subscription_enable();	
	}


///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// subscribe
	function subscribe() {
	  // Disable the button so it can't be changed while
	  // we process the permission request
	  //var pushButton = document.querySelector('.js-push-button');
	  //pushButton.disabled = true;

	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		serviceWorkerRegistration.pushManager.subscribe()
		  .then(function(subscription) {
			// The subscription was successful
			pnds.isPushEnabled = true;
		//	pushButton.textContent = 'Disable Push Messages';
		//	pushButton.disabled = false;

		//	showCurlCommand(subscription);

			// TODO: Send the subscription.subscriptionId and 
			// subscription.endpoint to your server
			// and save it to send a push message at a later date
			return send_subscription_to_server(subscription);
		  })
		  .catch(function(e) {
			if (Notification.permission === 'denied') {
			  // The user denied the notification permission which
			  // means we failed to subscribe and the user will need
			  // to manually change the notification permission to
			  // subscribe to push messages
			  window.Demo.debug.log('Permission for Notifications was denied');
			  pushButton.disabled = true;
			} else {
			  // A problem occurred with the subscription, this can
			  // often be down to an issue or lack of the gcm_sender_id
			  // and / or gcm_user_visible_only
			  window.Demo.debug.log('Unable to subscribe to push.', e);
			  pushButton.disabled = false;
			  pushButton.textContent = 'Enable Push Messages';
			}
		  });
	  });
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// Once the service worker is registered set the initial state
	function initialiseState() {
	  // Are Notifications supported in the service worker?
	  if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
		post_status('Notifications are not enabled.');
		post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: showNotification isn\'t supported by ServiceWorkerRegistration</small>');
		return;
	  }

	  // Check the current Notification permission.
	  // If its denied, it's a permanent block until the
	  // user changes the permission
	  if (Notification.permission === 'denied') {
		post_status('Notifications are not enabled.');
		post_message('<p>Problem: A user has blocked notifications.</p><small>Issue: Notification.permission is \'denied\'</small>');
		return;
	  }

	  // Check if push messaging is supported
	  if (!('PushManager' in window)) {
		post_status('Notifications are not enabled.');
		post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: Push messaging isn\'t supported.</small>');
		return;
	  }

	  // We need the service worker registration to check for a subscription
	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		// Do we already have a push message subscription?
		serviceWorkerRegistration.pushManager.getSubscription()
		  .then(function(subscription) {
		  
			if (!subscription) {
				// We aren’t subscribed to push, so set UI
				// to allow the user to request push subscription
				post_status('Notifications are not enabled.');
				add_subscription_enable();
				return; //// early return
			}

			// We're currently subscribed!!
			// Check that our cookie is present
			var cookie_val = Cookies.get(cookie_name);
			if (!cookie_val || cookie_val.length < 1) {			
				post_status('Notifications are not enabled.');
				post_message('<p>Problem: Notification issue. Please re-subscribe.</p><small>Issue: Subscribed but missing cookie</small>');
				internal_unsubscribe(subscription);
				return;
			}
			pnds.isPushEnabled = true;
			// Keep server in sync with the latest subscriptionId
			send_subscription_to_server(subscription);
			// Set UI to show that we are subscribed for push messages
			post_status('Notifications are enabled!');
			show_subscription(subscription);
		  })
		  .catch(function(err) {
    		post_status('Notifications are not enabled.');
			post_message('<p>Problem with current notification subscription</p><small>Issue: Error from Push Manager.</small>');
		  });
	  });
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// 	Enable user to add a subscription
	function add_subscription_enable() {
		$('#form-subscribe').collapse('show');
	}
	var subscribe_click = function(e) {
		$('#form-subscribe').collapse('hide');
		$('#form-authenticate').collapse('show');
    }
	var private_click = function(e) {
		// The user clicked the Private checkbox.
		// Set disabled state of the subscribe pushbutton
		if ($('#private').is(':checked')) {
			$('#btn-subscribe').removeAttr('disabled');
		} else {
			$('#btn-subscribe').attr('disabled', 'disabled');
		}
    }
	var authenticate_click = function(e) {
		// The user clicked Authenticate
		working(true);
    }
	
	
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// 	Show current subscription
	function show_subscription() {
	
	}
	
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// utilities
	function prepare_invisible() {
		// switch from css to js invisibility
		$('.invisible').hide().removeClass('invisible');
		// Initialize collapsed sections. See http://getbootstrap.com/javascript/#collapse
		$('.collapse').collapse('hide');
	}
	function add_events(){
        $('#btn-subscribe').on('click', subscribe_click);
        $('#btn-authenticate').on('click', authenticate_click);
		$('#private').on('change', private_click);
      }

	function post_status(msg) {
		$('#status').html(msg);	
	}
	function post_message(msg) { // msg can include html
		$('#butter-bar').show().html(msg);
	}
	function hide_message() {
		$('#butter-bar').hide();
	}
	function working(show) {
		if (show) {
			$('#working').modal({keyboard: false});
		} else {
			$('#working').modal('hide');
		}
	}
	
	

// pushbutton
	

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// MAIN LINE
	pnds.isPushEnabled = false;
	pnds.service_worker_url = "service-worker.js";
	add_events();
	prepare_invisible();
	// Check that service workers are supported, if so, progressively
	// enhance and add push messaging support, otherwise continue without it.
	if ('serviceWorker' in navigator) {
		navigator.serviceWorker.register(pnds.service_worker_url)
		.then(initialiseState);
	} else {
		// The specific problem is that service workers aren't supported. 
		post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: Service workers aren\'t supported</small>');
	}

});
