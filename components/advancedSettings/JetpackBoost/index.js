// Wordpress
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';

// Newfold
import { FeatureUpsell } from '@newfold/ui-component-library';

// Component
import SingleOption from './SingleOption';
import InstallActivatePluginButton from './InstallActivatePluginButton';

const JetpackBoost = ({ methods, constants }) => {

  const [fields, setFields] = useState([
    {
      id: 'critical-css',
      label: constants.text.jetpackBoostCriticalCssTitle,
      description: constants.text.jetpackBoostCriticalCssDescription,
      value: true,
      type: 'toggle',
      externalLink: true,
    },
    {
      id: 'render-blocking-js',
      label: constants.text.jetpackBoostRenderBlockingTitle,
      description: constants.text.jetpackBoostRenderBlockingDescription,
      value: true,
      type: 'toggle'
    },
    {
      id: 'minify-js',
      label: constants.text.jetpackBoostMinifyJsTitle,
      description: constants.text.jetpackBoostMinifyJsDescription,
      value: false,
      type: 'toggle',
      children: [
        {
          id: 'minify-js-excludes',
          label: constants.text.jetpackBoostExcludeJsTitle,
          description: '',
          value: '',
          type: 'textarea',
        }
      ]
    },
    {
      id: 'minify-css',
      label: constants.text.jetpackBoostMinifyCssTitle,
      description: constants.text.jetpackBoostMinifyCssDescription,
      value: false,
      type: 'toggle',
      children: [
        {
          id: 'minify-css-excludes',
          label: constants.text.jetpackBoostExcludeCssTitle,
          description: '',
          value: '',
          type: 'textarea',
        }
      ]
    }
  ]);

  const [moduleStatus, setModuleStatus] = useState(false);

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchOptions = async () => {
      try {
        setLoading(true);
  
        const response = await apiFetch({
          path: 'newfold-performance/v1/jetpack/settings',
        });
  
        const newFields = fields.map((element) => {
          const value = response[element.id];
          const updatedField = { ...element, value };
  
          if (element.children) {
            updatedField.children = element.children.map((child) => ({
              ...child,
              value: response[child.id],
            }));
          }
  
          return updatedField;
        });
  
        setFields(newFields);
        setModuleStatus(response.is_module_active);
      } catch (error) {
        console.error('Error fetching options:', error);
      } finally {
        setLoading(false);
      }
    };
  
    fetchOptions();
  }, [moduleStatus]);

  if (loading) {

    return <div>Loading...</div>;
  }

  return (
    <>
      {!moduleStatus ? (
        <div className="nfd-container-upsell" >
          <InstallActivatePluginButton methods={methods} constants={constants} setModuleStatus={setModuleStatus} />
          <FeatureUpsell>
            {fields.map((field) => {
              return (
                <SingleOption key={field.id} params={field} methods={methods} constants={constants} />
              );
            })}
          </FeatureUpsell>
        </div>
      ) : (
        <>
          {fields.map((field) => (
            <div className="nfd-container-single-option" style={{ marginBottom: "20px" }} key={field.id}>
              <SingleOption params={field} methods={methods} constants={constants} />

              {field.children && (
                <>
                  {field.children.map((subfield) => (
                    <div key={subfield.id}>
                      <SingleOption params={subfield} isChild={true} methods={methods} constants={constants} />
                    </div>
                  ))}
                </>
              )}
            </div>
          ))}
        </>
      )}
    </>
  );

}

export default JetpackBoost;