export const setCacheLevel = ( level ) => ( {
	type: 'SET_CACHE_LEVEL',
	level,
} );

export const setObjectCache = ( objectCache ) => ( {
	type: 'SET_OBJECT_CACHE',
	objectCache,
} );

export const pushNotification = ( id, message ) => ( {
	type: 'PUSH_NOTIFICATION',
	id,
	message,
} );

export const dismissNotification = ( id ) => ( {
	type: 'DISMISS_NOTIFICATION',
	id,
} );
