// Register service worker.js
// Based on https://github.com/GoogleChrome/samples/blob/gh-pages/push-messaging-and-notifications/main.js
// first execute config.js

'use strict';

$(document).ready(function () {
    // Use .ready may not be necessary, see http://goo.gl/aAhs  but we'll be
	// conservative and include it.
    function add_events(){
		$('#btn-subscribe').on('click', function (e) {
			if (pnds.isPushEnabled) {unsubscribe();} else {subscribe();}
		});
    }

	function sendSubscriptionToServer(subscription) {
	  // TODO: Send the subscription.subscriptionId and 
	  // subscription.endpoint to your server and save 
	  // it to send a push message at a later date
	  console.log('TODO: Implement sendSubscriptionToServer()');
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
	  var pushButton = document.querySelector('.js-push-button');
	  pushButton.disabled = true;
	  curlCommandDiv.textContent = '';

	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		// To unsubscribe from push messaging, you need to get the
		// subscription object, which you can call unsubscribe() on.
		serviceWorkerRegistration.pushManager.getSubscription().then(
		  function(pushSubscription) {
			// Check we have a subscription to unsubscribe
			if (!pushSubscription) {
			  // No subscription object, so set the state
			  // to allow the user to subscribe to push
			  pnds.isPushEnabled = false;
			  pushButton.disabled = false;
			  pushButton.textContent = 'Enable Push Messages';
			  return;
			}
			
			var subscriptionId = pushSubscription.subscriptionId;
			// TODO: Make a request to your server to remove
			// the subscriptionId from your data store so you 
			// don't attempt to send them push messages anymore

			// We have a subscription, so call unsubscribe on it
			pushSubscription.unsubscribe().then(function(successful) {
			  pushButton.disabled = false;
			  pushButton.textContent = 'Enable Push Messages';
			  pnds.isPushEnabled = false;
			}).catch(function(e) {
			  // We failed to unsubscribe, this can lead to
			  // an unusual state, so may be best to remove 
			  // the subscription id from your data store and 
			  // inform the user that you disabled push

			  window.Demo.debug.log('Unsubscription error: ', e);
			  pushButton.disabled = false;
			});
		  }).catch(function(e) {
			window.Demo.debug.log('Error thrown while unsubscribing from push messaging.', e);
		  });
	  });
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// subscribe
	function subscribe() {
	  // Disable the button so it can't be changed while
	  // we process the permission request
	  var pushButton = document.querySelector('.js-push-button');
	  pushButton.disabled = true;

	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		serviceWorkerRegistration.pushManager.subscribe()
		  .then(function(subscription) {
			// The subscription was successful
			pnds.isPushEnabled = true;
			pushButton.textContent = 'Disable Push Messages';
			pushButton.disabled = false;

			showCurlCommand(subscription);

			// TODO: Send the subscription.subscriptionId and 
			// subscription.endpoint to your server
			// and save it to send a push message at a later date
			return sendSubscriptionToServer(subscription);
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
		window.Demo.debug.log('Notifications aren\'t supported.');
		return;
	  }

	  // Check the current Notification permission.
	  // If its denied, it's a permanent block until the
	  // user changes the permission
	  if (Notification.permission === 'denied') {
		window.Demo.debug.log('The user has blocked notifications.');
		return;
	  }

	  // Check if push messaging is supported
	  if (!('PushManager' in window)) {
		window.Demo.debug.log('Push messaging isn\'t supported.');
		return;
	  }

	  // We need the service worker registration to check for a subscription
	  navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
		// Do we already have a push message subscription?
		serviceWorkerRegistration.pushManager.getSubscription()
		  .then(function(subscription) {
			// Enable any UI which subscribes / unsubscribes from
			// push messages.
			var pushButton = document.querySelector('.js-push-button');
			pushButton.disabled = false;

			if (!subscription) {
			  // We aren’t subscribed to push, so set UI
			  // to allow the user to enable push
			  return;
			}

			// Keep your server in sync with the latest subscriptionId
			sendSubscriptionToServer(subscription);
			
			showCurlCommand(subscription);

			// Set your UI to show they have subscribed for
			// push messages
			pushButton.textContent = 'Disable Push Messages';
			pnds.isPushEnabled = true;
		  })
		  .catch(function(err) {
			window.Demo.debug.log('Error during getSubscription()', err);
		  });
	  });
	}


///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// utilities
	function post_message(msg) { // msg can include html
		$('#butter-bar').show().html(msg);
	}
	function hide_message() {
		$('#butter-bar').hide();
	}

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////
//
// MAIN LINE
	pnds.isPushEnabled = false;
	pnds.service_worker_url = "service-worker.js";
	add_events();
	// Check that service workers are supported, if so, progressively
	// enhance and add push messaging support, otherwise continue without it.
	if (! 'serviceWorker' in navigator) {
		navigator.serviceWorker.register(pnds.service_worker_url)
		.then(initialiseState);
	} else {
		// The specific problem is that service workers aren't supported. 
		post_message('<p>Problem: this browser does not support notifications. <br />Please see below. </p><small>Issue: Service workers aren\'t supported</small>');
	}

});
