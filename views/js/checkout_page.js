window.datacue.identify(window.datacueUserId);

// track the event
window.datacue.track({
  type: 'checkout',
  subtype: 'started',
  cart: window.datacueCart,
  cart_link: window.datacueCartLink
});
