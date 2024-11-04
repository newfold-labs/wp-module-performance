import {  Container } from '@newfold/ui-component-library'

import JetpackBoost from './JetpackBoost/index';

const AdvancedSettings = ( { methods, constants } ) => {
  return (
    <Container.SettingsField
      title={constants.text.cacheAdvancedSettingsTitle}
      description={constants.text.cacheAdvancedSettingsDescription}
    >
      <JetpackBoost methods={methods} constants={constants} />
    </Container.SettingsField>
  );
}

export default AdvancedSettings;