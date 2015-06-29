// Register service-main.js
// Based on https://github.com/GoogleChrome/samples/blob/gh-pages/push-messaging-and-notifications/main.js
// first execute config.js
//
// ServiceWorker info: https://jakearchibald.github.io/isserviceworkerready/
// Jake's blog: http://jakearchibald.com/

'use strict';

var pndso = new function() {
    this.cookie_notify = "PushNotifyDocuSign";  // yes or no
	this.cookie_notify_ID = "PushNotifyDocuSignID"; // unique id
	this.user_email = null;
	this.accounts = null;
	this.subscribed_accounts = null;	
	this.instance = this;
	this.subscription = null;
	
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
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// Once the service worker is registered set the initial state
	this.initialiseState_start = function() {
		pndso.initialiseState.call(pndso);
	}
	this.initialiseState = function() {
		// Are Notifications supported in the service worker?
		if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
			this.post_status('Notifications are not enabled.');
			this.post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: showNotification isn\'t supported by ServiceWorkerRegistration</small>');
			return;
		}

		// Check the current Notification permission.
		// If its denied, it's a permanent block until the user changes the permission
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

		// We need the service worker registration to check for a subscription
		navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
			// Do we already have a push message subscription?
			// See https://developer.mozilla.org/en-US/docs/Web/API/PushManager/PushManager.getSubscription
			serviceWorkerRegistration.pushManager.getSubscription().then(function(subscription) {
				if (!subscription) {
					// We aren’t subscribed to push, so set UI to allow the user to request push subscription
					pndso.post_status('Notifications are not enabled.');
					pndso.add_subscription_enable();
					pndso.working(false);
					return; //// early return
				}

				// We're currently subscribed!!
				pndso.subscription = subscription;

				// Check that our cookies are good
				var cookie_notify = Cookies.get(pndso.cookie_notify),
					cookie_notify_ID = Cookies.get(pndso.cookie_notify_ID);
				if (!cookie_notify_ID || cookie_notify_ID.length < 10 || !cookie_notify || cookie_notify!== "yes") {			
					pndso.post_status('Notifications are not enabled.');
					pndso.post_message('<p>Problem: Notification issue. Please re-subscribe.</p><small>Issue: Subscribed but missing cookie</small>');
					pndso.internal_unsubscribe.call(pndso);
					return;
				}
				pnds.isPushEnabled = true;
				// Keep server in sync with the latest subscriptionId
				pndso.refresh_subscription_to_server.call(pndso);
			})
			.catch(function(err) {
				pndso.post_status('Notifications are not enabled.');
				pndso.post_message('<p>Problem with current notification subscription</p><small>Issue: Error from Push Manager.</small>');
				pndso.add_subscription_enable();
				pndso.working(false);
			});
		});
	}

	this.refresh_subscription_to_server = function() {
		// Refresh the server since the subscription end point could have changed 
		// If it doesn't work then we need to remove the local subscription
		//
		// Side effect: set subscribed_accounts
		data = {subscription: this.subscription.endpoint};
		
		$.ajax(pnds.api_url + "?op=refresh",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 contentType: "application/json; charset=UTF-8",
			 processData: false,
			 data: JSON.stringify(data),
			 context: this})
		.done(function(data, textStatus, jqXHR){
			this.subscribed_accounts = data.subscribed_accounts;
			this.subscribed();
			this.working(false);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			this.unsubscribe(); // Unsubscribe from the subscription object
			this.isPushEnabled = false;			
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
			$('#pw').val('').keydown(pndso.pw_keydown ); // clear the pw and set keydown handler
			
			$('#form-authenticate').collapse('show');})	

		pndso.hide_message();
		$('#form-subscribe').collapse('hide');		
    }
//
// 	3a. <cr> in the pw field triggers form
	this.pw_keydown = function(e) {
		if (e.which === 13) {
			pndso.authenticate_click(false);
		}
	}
//
// 	4. The user clicked Authenticate
	this.authenticate_click = function(e) {
		e && e.preventDefault(); // Don't submit to the server
		pndso.working(true);
		pndso.hide_message();
		pndso.post_status();
		pndso.user_email = $('#email').val();
		
		$.ajax(pnds.api_url + "?op=authenticate",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 data: {email: pndso.user_email, pw: $('#pw').val()}})
		.done(function(data, textStatus, jqXHR){
			pndso.authenticated(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			if (jqXHR.status === 400 && jqXHR.responseJSON && jqXHR.responseJSON.hasOwnProperty('api')) {
				// Error message from api
				pndso.post_message("<h2>Problem: " + jqXHR.responseJSON.msg + "</h2>");
			} else {
				pndso.post_message("<h2>Problem: " + textStatus + "</h2>"); 
			}
		})
		.always(function() {
			$('#pw').val(""); // clear pw value
			pndso.working(false);
		});		
		return false;
    }
//
// 	5. 	The user authenticated successfully.
// Show the do-subscribe form with the potential subscription information
	this.authenticated = function(data) {
		// Store the accounts information for future use
		pndso.accounts = data.accounts;
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
			$('#account-table caption').text("Account Information for " + pndso.user_email); 
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
//      First, create a subscription for the service worker internal to the browser	
	this.do_subscribe_click = function(e) {
		// The user clicked the subscribe button
		e.preventDefault(); // Don't submit to the server
		pndso.working(true);
		pndso.hide_message();
		pndso.post_status();
		pndso.subscribe();
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
				pndso.subscription = subscription;
				pndso.send_subscription_to_server.call(pndso);
				return;
			})
			.catch(function(e) {
				pnds.isPushEnabled = false;
				if (Notification.permission === 'denied') {
					// The user denied the notification permission which
					// means we failed to subscribe and the user will need
					// to manually change the notification permission to
					// subscribe to push messages
					pndso.subscription_failed("Permission to receive push notifications was denied.");
				} else {
					// A problem occurred with the subscription, this can
					// often be down to an issue or lack of the gcm_sender_id
					// and / or gcm_user_visible_only
					pndso.subscription_failed("Unable to subscribe to push notification.");
				}
			});
		});
	}	
//
// 	8.  Browser subscription worked. Send the subscription to the server.
//      Side effect: sets this.subscribed_accounts
	this.send_subscription_to_server = function() {
		// We try to get the server to subscribe us to DocuSign. 
		// If it doesn't work then we need to remove the local subscription
		var browser = browser_detect.split(" ",1)[0], // Chrome browser notify is different from standard
			requested_accounts = [];
		
		pndso.accounts.forEach(function(account, i, a){
			if (account.available) {
				requested_accounts.push(account);
			}
		})
		
		data = {
			email: pndso.user_email, 
			pw: $('#pw').val(), 
			subscription: this.subscription.endpoint, 
			browser: browser, 
			accounts: requested_accounts};
		
		$.ajax(pnds.api_url + "?op=subscribe",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 contentType: "application/json; charset=UTF-8",
			 processData: false,
			 data: JSON.stringify(data),
			 context: this})
		.done(function(data, textStatus, jqXHR){
			this.subscribed_accounts = data.subscribed_accounts;
			this.subscribed(data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			this.unsubscribe(); // Unsubscribe from the subscription object
			this.isPushEnabled = false;			
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
	this.subscribed = function(data) {
		this.post_status("Subscribed!" + JSON.stringify(data));
		// Show unsubscribe form. The subscribe button form may or may not be visible
		$('#form-subscribe-button').on('hidden.bs.collapse', function (e) {
			$('#form-unsubscribe').collapse('show');})	

		if ($('#form-subscribe-button').is(':visible')) {
			$('#form-subscribe-button').collapse('hide');		
		} else {
			$('#form-unsubscribe').collapse('show');
		}
	}
//
// 	9.  Browser subscription failed....		
	this.subscription_failed = function(msg) {
		this.post_message(msg);
		this.working(false);
	}
	
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// unsubscribe
	this.unsubscribe_click = function(e) {
		// The user clicked the unsubscribe button
		e.preventDefault(); // Don't submit to the server
		pndso.unsubscribe.call(pndso);
		return false;
	}

	this.unsubscribe = function() {
		this.working(true);
		this.hide_message();
		this.post_status();	

		this.accounts = null;
		navigator.serviceWorker.ready.then(function serviceWorker_ready(serviceWorkerRegistration) {
			// To unsubscribe from push messaging, we need to get the
			// subscription object, which you can call unsubscribe() on.
			serviceWorkerRegistration.pushManager.getSubscription().then(
				function pushManager_getSubscription(subscription) {
					// Check we have a subscription to unsubscribe
					if (!subscription) {
						// No subscription object, so reset state
						pnds.isPushEnabled = false;
						pndso.add_subscription_enable.call(pndso);
						pndso.working(false);
						return;
					}
				
					// We have a subscription, so call unsubscribe on it
					pndso.subscription = subscription;
					pndso.internal_unsubscribe.call(pndso);
				}
			).catch(function(e) {
				pndso.post_message('<p>Unsubscribed.</p><small>Issue: Problem with Push Manager</small>');
				pndso.working(false);
			});
		});
	}

	this.internal_unsubscribe = function() {
		pndso.subscription.unsubscribe().then(function subscription_unsubscribe(successful) {
			pndso.send_unsubscribe_to_server.call(pndso);
			pndso.post_message('<p>Unsubscribed.</p>');
		}).catch(function catch_unsubscribe(e) {
			// We failed to unsubscribe, this can lead to an unusual state, so may be best to remove 
			// the subscription id from your data store and inform the user that you disabled push
			pndso.send_unsubscribe_to_server.call(pndso);
			pndso.post_message('<p>Unsubscribed.</p><small>Issue: Problem with unsubscribing</small>');
		});
	}
	
	this.send_unsubscribe_to_server = function() {
		// Tell the server we've unsubscribed 
		data = {subscription: this.subscription.endpoint};
		
		$.ajax(pnds.api_url + "?op=unsubscribe",  // Ajax Methods: https://github.com/jquery/api.jquery.com/issues/49
			{method: "POST",
			 contentType: "application/json; charset=UTF-8",
			 processData: false,
			 data: JSON.stringify(data),
			 context: this})
		.done(function(data, textStatus, jqXHR){
			this.unsubscribed();
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			this.unsubscribed();
			this.isPushEnabled = false;			
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

	this.unsubscribed = function() {
		pnds.isPushEnabled = false;
		this.subscription = null;
		this.add_subscription_enable();
	}

	
///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// utilities
//
	this.prepare_invisible = function() {
		// switch from css to js invisibility
		$('.invisible').hide().removeClass('invisible');
		// Initialize collapsed sections. See http://getbootstrap.com/javascript/#collapse
		$('.collapse').collapse('hide');
	}
	this.add_events = function(){
        $('#btn-subscribe').on('click', this.subscribe_click);
		$('#btn-unsubscribe').on('click', this.unsubscribe_click);
        $('#btn-authenticate').on('click', this.authenticate_click);
		$('#btn-do-subscribe').on('click', this.do_subscribe_click);
		$('#private').on('change', this.private_click);
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
			this.working(true);
			navigator.serviceWorker.register(pnds.service_worker_url)
			.then(this.initialiseState_start);
		} else {
			// The specific problem is that service workers aren't supported. 
			this.post_message('<p>Problem: this browser does not support notifications. <br />Please see the browser support information below. </p><small>Issue: Service workers aren\'t supported</small>');
		}
	}
}


// mainline
$(document).ready(function () {
	pndso.initialize();
});
