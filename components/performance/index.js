import { default as CacheSettings } from '../cacheSettings/';
import { default as ClearCache } from '../clearCache/';
import { default as defaultText } from './defaultText';

/**
 * Performance Module
 * For use in brand plugin apps to display performance page and settings
 * 
 * @param {*} props 
 * @returns 
 */
const Performance = ({methods, constants, Components, ...props}) => {
    const { store, setStore } = methods.useContext(methods.AppStore);
    const [ isError, setError ] = methods.useState(false);

	let notify = methods.useNotification();

	// set default text if not provided
	constants.text = Object.assign(defaultText, constants.text);

    const makeNotice = (id, title, description, variant="success", duration=false) => {
        notify.push(`performance-notice-${id}`, {
            title,
            description: (
                <span>
                    {description}
                </span>
            ),
            variant,
            autoDismiss: duration,
        });
    };
    constants.store = store;
    methods.makeNotice = makeNotice;
    methods.setStore = setStore;
    methods.setError = setError;

	return (
        <>
            <Components.SectionContent separator={true} className={'newfold-cache-settings'}>
                <CacheSettings
                    methods={methods}
                    constants={constants}
                    Components={Components}
                />
            </Components.SectionContent>
            <Components.SectionContent className={'newfold-clear-cache'}>
                <ClearCache
                    methods={methods}
                    constants={constants}
                    Components={Components}
                />
            </Components.SectionContent>
        </>
	);

};

export default Performance;