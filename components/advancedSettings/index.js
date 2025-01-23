// Newfold
import { Container } from '@newfold/ui-component-library';

// Components
import JetpackBoost from './JetpackBoost/index';

const AdvancedSettings = ( { methods, constants } ) => {
	return (
		<Container.SettingsField
			title={ constants.text.performanceAdvancedSettingsTitle }
			description={
				constants.text.performanceAdvancedSettingsDescription
			}
		>
			<JetpackBoost methods={ methods } constants={ constants } />
		</Container.SettingsField>
	);
};

export default AdvancedSettings;
