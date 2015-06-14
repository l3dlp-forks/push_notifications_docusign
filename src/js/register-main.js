// Register service-main.js
// Based on https://github.com/GoogleChrome/samples/blob/gh-pages/push-messaging-and-notifications/main.js
// first execute config.js

'use strict';

var pndso = new function() {
    this.cookie_name = 'push_subscriber';
	this.user_email = null;
	this.accounts = null;
	this.instance = this;
	
	this.send_unsubscribe_to_server = function(subscription) {
	  // TODO: Send the subscription.subscriptionId and 
	  // subscription.endpoint to your server and save 
	  // it to send a push message at a later date
	  console.log('TODO: Implement send_subscription_to_server()');
	}

	this.showCurlCommand = function(subscription) {
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
	this.unsubscribe_click = function(e) {
		// The user clicked the unsubscribe button
		e.preventDefault(); // Don't submit to the server
		this.unsubscribe();
		return false;
	}

	this.unsubscribe = function() {
		this.working(true);
		this.hide_message();
		this.post_status();	

		this.accounts = null;
		navigator.serviceWorker.ready.then(functionserviceWorker_ready(serviceWorkerRegistration) {
			// To unsubscribe from push messaging, you need to get the
			// subscription object, which you can call unsubscribe() on.
			serviceWorkerRegistration.pushManager.getSubscription().then(
				function pushManager_getSubscription(subscription) {
					// Check we have a subscription to unsubscribe
					if (!subscription) {
						// No subscription object, so reset state
						pnds.isPushEnabled = false;
						this.add_subscription_enable();
						this.working(false);
						return;
					}
				
					// We have a subscription, so call unsubscribe on it
					this.internal_unsubscribe(subscription);
				}
			).catch(function(e) {
				this.post_message('<p>Unsubscribed.</p><small>Issue: Problem with Push Manager</small>');
				this.working(false);
			});
		});
	}

	this.internal_unsubscribe = function(subscription) {
		subscription.unsubscribe().then(function subscription_unsubscribe(successful) {
			this.unsubscribed(subscription);
			this.post_message('<p>Unsubscribed.</p>');
			this.working(false);
		}).catch(function catch_unsubscribe(e) {
			// We failed to unsubscribe, this can lead to
			// an unusual state, so may be best to remove 
			// the subscription id from your data store and 
			// inform the user that you disabled push
			this.unsubscribed(subscription);
			this.post_message('<p>Unsubscribed.</p><small>Issue: Problem with unsubscribing</small>');
			this.working(false);
		});
	}
	
	this.unsubscribed = function(subscription) {
		pnds.isPushEnabled = false;
		this.send_unsubscribe_to_server(subscription);
		this.add_subscription_enable();	
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// Once the service worker is registered set the initial state
	this.initialiseState = function() {
	  // Are Notifications supported in the service worker?
	  if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
		this.post_status('Notifications are not enabled.');
		this.post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: showNotification isn\'t supported by ServiceWorkerRegistration</small>');
		return;
	  }

	  // Check the current Notification permission.
	  // If its denied, it's a permanent block until the
	  // user changes the permission
	  if (Notification.permission === 'denied') {
		this.post_status('Notifications are not enabled.');
		this.post_message('<p>Problem: A user has blocked notifications.</p><small>Issue: Notification.permission is \'denied\'</small>');
		return;
	  }

	  // Check if push messaging is supported
	  if (!('PushManager' in window)) {
		this.post_status('Notifications are not enabled.');
		this.post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: Push messaging isn\'t supported.</small>');
		return;
	  }

	  this.working(true); // starting async operations
	  setTimeout(pndso.initialiseState_start2, 0);  // Let modal window paint	  
	}
	  
	
	this.initialiseState_start2 = function(){
		pndso.initialiseState2.call(pnds);
	}
		
	this.initialiseState2 = function(){
console.log(1);
	// We need the service worker registration to check for a subscription
	navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		// Do we already have a push message subscription?
		serviceWorkerRegistration.pushManager.getSubscription()
	.then(function(subscription) {
console.log(n);  
		if (!subscription) {
			// We aren’t subscribed to push, so set UI
			// to allow the user to request push subscription
console.log(2);
			this.post_status('Notifications are not enabled.');
console.log(3);
			this.add_subscription_enable();
console.log(4);
			this.working(false);
			return; //// early return
		}

		// We're currently subscribed!!
		// Check that our cookie is present
		var cookie_val = Cookies.get(cookie_name);
		if (!cookie_val || cookie_val.length < 1) {			
console.log(5);
			this.post_status('Notifications are not enabled.');
			this.post_message('<p>Problem: Notification issue. Please re-subscribe.</p><small>Issue: Subscribed but missing cookie</small>');
console.log(6);
			this.internal_unsubscribe(subscription);
console.log(7);
			return;
		}
console.log(8);
		pnds.isPushEnabled = true;
		// Keep server in sync with the latest subscriptionId
console.log(9);
		this.send_subscription_to_server(subscription);
		// Set UI to show that we are subscribed for push messages
console.log(10);
		this.post_status('Notifications are enabled!');
console.log(11);
		this.show_subscription(subscription);
console.log(12);
		this.working(false);
	  })
	  .catch(function(err) {
console.log("13, %o", err);
		this.post_status('Notifications are not enabled.');
		this.post_message('<p>Problem with current notification subscription</p><small>Issue: Error from Push Manager.</small>');
		this.add_subscription_enable();
		this.working(false);
	  });
  });
  }


///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
//  Subscription functions
//
// 	1. Enable user to start the subscription process:
//     Show the form that confirms this is a private browser
	this.add_subscription_enable = function () {
		// if the unsubscribe form is visible, then first make it go away
		$('#form-unsubscribe').on('hidden.bs.collapse', function (e) {
			$('#form-subscribe').collapse('show');})	

		if ($('#form-unsubscribe').is(':visible')) {
			$('#form-unsubscribe').collapse('hide');		
		} else {
			$('#form-subscribe').collapse('show');
		}
	}
//
// 	2. The user clicked the Private checkbox. 
//     Enable/disable the subscribe pushbutton
	this.private_click = function(e) {
		if ($('#private').is(':checked')) {
			$('#btn-subscribe').removeAttr('disabled');
		} else {
			$('#btn-subscribe').attr('disabled', 'disabled');
		}
    }
//
// 	3. Show the Authentication form to get the user's email and pw
	this.subscribe_click = function(e) {
		$('#form-subscribe').on('hidden.bs.collapse', function (e) {
			$('#form-authenticate').collapse('show');})	

		this.hide_message();
		$('#form-subscribe').collapse('hide');		
    }
//
// 	4. The user clicked Authenticate
	var authenticate_click = function(e) {
		e.preventDefault(); // Don't submit to the server
		this.working(true);
		this.hide_message();
		this.post_status();
		user_email = $('#email').val();
		
		$.ajax(pnds.api_url + "?op=authenticate",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 data: {email: user_email, pw: $('#pw').val()}})
		.done(function(data, textStatus, jqXHR){
			this.authenticated(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			if (jqXHR.status === 400 && jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('api')) {
				// Error message from api
				this.post_message("<h2>Problem: " + jqXHR.responseJSON.msg + "</h2>");
			} else {
				this.post_message("<h2>Problem: " + textStatus + "</h2>"); 
			}
		})
		.always(function() {
			$('#pw').val(""); // clear pw value
			this.working(false);
		});		
		return false;
    }
//
// 	5. 	The user authenticated successfully.
// Show the do-subscribe form with the potential subscription information
	this.authenticated = function(data) {
		// Store the accounts information for future use
		this.accounts = data.accounts;
		// Populate the form
		var add_admin = false, can_subscribe = false;
		$('#account-table tbody').html(""); // clear any prior information
		data.accounts.forEach(function(account, i, a){
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
			$('#post-account-table').html("<p>* To receive notifications for these accounts, please add the system user " + data.admin_email +
				" as an administrator.</p>");
		}
		if (!can_subscribe) {
			$('#post-account-table').html("<p>Problem: The system user, " + data.admin_email +
				" does not have admin rights for any of your accounts. Solution: add the system user " +
				"as an Administrator to your accounts.</p>");
			$('#btn-do-subscribe').addAttr('disabled');			
		}
		
		// Show the modal
		$('#form-authenticate').on('hidden.bs.collapse', function (e) {
			$('#form-subscribe-button').collapse('show');})	

		$('#form-authenticate').collapse('hide');		
	}
//
// 	6. 	The user wants to subscribe.
//      First, create a subscription for the service worked internal to the browser	
	var this.do_subscribe_click = function(e) {
		// The user clicked the subscribe button
		e.preventDefault(); // Don't submit to the server
		this.working(true);
		this.hide_message();
		this.post_status();
		this.subscribe();
		return false;
	}
//
// 	7.  Subscribe within the browser
	this.subscribe = function() {
		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			serviceWorkerRegistration.pushManager.subscribe({userVisibleOnly: true})
			.then(function(subscription) {
				// The subscription was successfully created.
				// We are called with the subscription
				pnds.isPushEnabled = true;
				this.send_subscription_to_server(subscription);
				return;
			})
			.catch(function(e) {
				pnds.isPushEnabled = false;
				if (Notification.permission === 'denied') {
					// The user denied the notification permission which
					// means we failed to subscribe and the user will need
					// to manually change the notification permission to
					// subscribe to push messages
					this.subscription_failed("Permission to receive push notifications was denied.");
				} else {
					// A problem occurred with the subscription, this can
					// often be down to an issue or lack of the gcm_sender_id
					// and / or gcm_user_visible_only
					this.subscription_failed("Unable to subscribe to push notification.");
				}
			});
		});
	}	
//
// 	8.  Browser subscription worked. Send the subscription to the server.
	this.send_subscription_to_server = function(subscription) {
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
			this.subscribed(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			this.unsubscribe(); // Unsubscribe from the subscription object
			pnds.isPushEnabled = false;			
			if (jqXHR.status === 400 && jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('api')) {
				// Error message from api
				this.post_message("<h2>Problem: " + jqXHR.responseJSON.msg + "</h2>");
			} else {
				this.post_message("<h2>Problem: " + textStatus + "</h2>"); 
			}
		})
		.always(function() {
			this.working(false);
		});		
	}
//
// 	9.  Fully subscribed. Post info to user	
	subscribed = function(data) {
		this.post_status("Subscribed!" + JSON.stringify(data));
		
		// Show unsubscribe form
		$('#form-subscribe-button').on('hidden.bs.collapse', function (e) {
			$('#form-unsubscribe').collapse('show');})	

		$('#form-subscribe-button').collapse('hide');		
	}
//
// 	9.  Browser subscription failed....		
	this.subscription_failed = function(msg) {
		this.post_message(msg);
		this.working(false);
	}
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// 	Show current subscription
	this.function show_subscription() {
	
	}
	
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// utilities
	this.prepare_invisible = function() {
		// switch from css to js invisibility
		$('.invisible').hide().removeClass('invisible');
		// Initialize collapsed sections. See http://getbootstrap.com/javascript/#collapse
		$('.collapse').collapse('hide');
	}
	this.add_events = function(){
        $('#btn-subscribe').on('click', subscribe_click);
		$('#btn-unsubscribe').on('click', unsubscribe_click);
        $('#btn-authenticate').on('click', authenticate_click);
		$('#btn-do-subscribe').on('click', do_subscribe_click);
		$('#private').on('change', private_click);
      }

	this.post_status = function(msg) {
		$('#status').html(msg);	
	}
	this.post_message = function(msg) { // msg can include html
		$('#butter-bar').show().html(msg);
	}
	this.hide_message = function() {
		$('#butter-bar').hide();
	}
	this.working = function(show) {
		if (show) {
			$('#working').modal({keyboard: false, backdrop: 'static'});
		} else {
			$('#working').modal('hide');
		}
	}
	

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// MAIN LINE
    this.initialize = function() {
		pnds = {};
		pnds.api_url = 'api.php'; // the api.php url relative to the index page
		pnds.isPushEnabled = false;
		pnds.service_worker_url = "service-worker.js";
		this.add_events();
		this.prepare_invisible();
		// Check that service workers are supported, if so, progressively
		// enhance and add push messaging support, otherwise continue without it.
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register(pnds.service_worker_url)
			.then(this.initialiseState);
		} else {
			// The specific problem is that service workers aren't supported. 
			this.post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: Service workers aren\'t supported</small>');
		}
		}
}


// mainline
$(document).ready(function () {
	pndo.initalize();
}
	
	
