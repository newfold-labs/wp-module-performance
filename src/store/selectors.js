export const getCacheLevel = ( state ) => state.cacheLevel;

export const getObjectCache = ( state ) => state.objectCache;

export const getNotifications = ( state ) => state.feed;

export const getVisibleNotifications = ( state ) =>
	Object.entries( state.feed )
		.filter( ( [ , value ] ) => value !== null )
		.map( ( [ key, value ] ) => [ key, value ] );
