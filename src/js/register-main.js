// Register service-main.js
// Based on https://github.com/GoogleChrome/samples/blob/gh-pages/push-messaging-and-notifications/main.js
// first execute config.js

'use strict';

$(document).ready(function () {
    // Use .ready may not be necessary, see http://goo.gl/aAhs  but we'll be
	// conservative and include it.
    
	var cookie_name = 'push_subscriber',
		user_email,
		accounts;
	
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
		working(true);
		hide_message();
		post_status();	

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
						working(false);
						return;
					}
				
					// We have a subscription, so call unsubscribe on it
					internal_unsubscribe(subscription);
					working(false);
				}
			).catch(function(e) {
				window.Demo.debug.log('Problem from Push Manager.', e);
				post_message('<p>Unsubscribed.</p><small>Issue: Problem with Push Manager</small>');
				working(false);
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
		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			serviceWorkerRegistration.pushManager.subscribe({userVisibleOnly: true})
			.then(function(subscription) {
				// The subscription was successfully created.
				// We are called with the subscription
				pnds.isPushEnabled = true;
				send_subscription_to_server(subscription);
				return;
			})
			.catch(function(e) {
				pnds.isPushEnabled = false;
				if (Notification.permission === 'denied') {
					// The user denied the notification permission which
					// means we failed to subscribe and the user will need
					// to manually change the notification permission to
					// subscribe to push messages
					subscription_failed("Permission to receive push notifications was denied.");
				} else {
					// A problem occurred with the subscription, this can
					// often be down to an issue or lack of the gcm_sender_id
					// and / or gcm_user_visible_only
					subscription_failed("Unable to subscribe to push notification.");
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

	  working(true); // starting async operations
	  // We need the service worker registration to check for a subscription
	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		// Do we already have a push message subscription?
		serviceWorkerRegistration.pushManager.getSubscription()
		  .then(function(subscription) {
		  
			if (!subscription) {
				// We aren’t subscribed to push, so set UI
				// to allow the user to request push subscription
				post_status('Notifications are not enabled.');
				working(false);
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
				working(false);
				return;
			}
			pnds.isPushEnabled = true;
			// Keep server in sync with the latest subscriptionId
			send_subscription_to_server(subscription);
			// Set UI to show that we are subscribed for push messages
			post_status('Notifications are enabled!');
			show_subscription(subscription);
			working(false);
		  })
		  .catch(function(err) {
    		post_status('Notifications are not enabled.');
			post_message('<p>Problem with current notification subscription</p><small>Issue: Error from Push Manager.</small>');
		    working(false);
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
		$('#form-subscribe').on('hidden.bs.collapse', function (e) {
			$('#form-authenticate').collapse('show');})	

		$('#form-subscribe').collapse('hide');		
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
		e.preventDefault(); // Don't submit to the server
		working(true);
		hide_message();
		post_status();
		user_email = $('#email').val();
		
		$.ajax(pnds.api_url + "?op=authenticate",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 data: {email: user_email, pw: $('#pw').val()}})
		.done(function(data, textStatus, jqXHR){
			authenticated(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			if (jqXHR.status === 400 && jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('api')) {
				// Error message from api
				post_message("<h2>Problem: " + jqXHR.responseJSON.msg + "</h2>");
			} else {
				post_message("<h2>Problem: " + textStatus + "</h2>"); 
			}
		})
		.always(function() {
			working(false);
		});		
		return false;
    }
	
	function authenticated(data) {
		// The user authenticated successfully.
		// Show the do-subscribe form with the potential subscription information
		//
		// Store the accounts information
		accounts = data.accounts;
		// Show the modal
		$('#form-authenticate').on('hidden.bs.collapse', function (e) {
			$('#form-subscribe-button').collapse('show');})	

		$('#form-authenticate').collapse('hide');		
	
		// Populate the form
		var add_admin = false, can_subscribe = false;
		data.accounts.forEach(function(account, i, accounts){
			$('#account-table tbody').append(
				"<tr><td>" + account.account_name + "</td><td>" +
					(account.available ? "yes" : "no*") + "</td></tr>");
				if (account.available) {
					can_subscribe = true;
				} else {
					add_admin = true;
				}
			$('#account-table caption').text("Account Information for " + user_email); 
			})
		if (add_admin) {
			$('#post-account-table').html("<p>* For these accounts, add the system user " + data.admin_email +
				" as an Administrator to the account.</p>");
		}
		if (!can_subscribe) {
			$('#post-account-table').html("<p>Problem: The system user, " + data.admin_email +
				" does not have admin rights for any of your accounts. Solution: add the system user " +
				"as an Administrator to your accounts.</p>");
			$('#btn-do-subscribe').addAttr('disabled');			
		}
	}
	
	var do_subscribe_click = function(e) {
		// The user clicked the subscribe button
		e.preventDefault(); // Don't submit to the server
		working(true);
		hide_message();
		post_status();
		subscribe();
		return false;
	}

	function send_subscription_to_server(subscription) {
		// We try to get the server to subscribe us to DocuSign. 
		// If it doesn't work then we need to remove the local subscription
		
		data = {subscription: subscription.endpoint, accounts: accounts};
		
		$.ajax(pnds.api_url + "?op=subscribe",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 contentType: "application/json; charset=UTF-8",
			 processData: false,
			 data: JSON.stringify(data),
			 context: subscription})
		.done(function(data, textStatus, jqXHR){
			subscribed(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			this.unsubscribe(); // Unsubscribe from the subscription object
			pnds.isPushEnabled = false;			
			if (jqXHR.status === 400 && jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('api')) {
				// Error message from api
				post_message("<h2>Problem: " + jqXHR.responseJSON.msg + "</h2>");
			} else {
				post_message("<h2>Problem: " + textStatus + "</h2>"); 
			}
		})
		.always(function() {
			working(false);
		});		
	}
	
	function subscribed(data) {
		post_status("Subscribed!" + JSON.stringify(data));
	}
	
	function subscription_failed(msg) {
		post_message(msg);
		working(false);
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
		$('#btn-do-subscribe').on('click', do_subscribe_click);
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
			$('#working').modal({keyboard: false, backdrop: 'static'});
		} else {
			$('#working').modal('hide');
		}
	}
	
	

// pushbutton
	

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// MAIN LINE
	pnds = {};
	pnds.api_url = 'api.php'; // the api.php url relative to the index page
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
