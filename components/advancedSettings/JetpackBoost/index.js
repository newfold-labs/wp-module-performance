import React, { useEffect, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

import { FeatureUpsell, ToggleField } from '@newfold/ui-component-library';

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

  const style = {};

  const [is_module_active, setModuleStatus] = useState(false);


  const [loading, setLoading] = useState(true); // Nuovo stato per il caricamento

  useEffect(() => {
    setLoading(true) // Inizia il caricamento
    apiFetch({
      path: 'newfold-performance/v1/jetpack/get_options'
    })
      .then(async (response) => {

        const newFields = []
        fields.forEach((element) => {
          const id = element.id
          const value = response[id] // Assign the value from database to the const value
          let newField = element // Assign the current field to a variable
          newField.value = value // Assign the db value fo the variable just created


          if (element.children) {
            const newChildrenFields = []
            element.children.forEach((childElement) => {
              const id = childElement.id

              const value = response[id] // Assign the value from database to the const value

              let newChildField = childElement // Assign the current field to a variable
              newChildField.value = value // Assign the db value fo the variable just created
              newChildrenFields.push(newChildField) // Push the new field to the array that will be used to update the status
            })
            newField.children = newChildrenFields;
          }

          newFields.push(newField) // Push the new field to the array that will be used to update the status
        })

        setFields(newFields)
        setModuleStatus(response.is_module_active)
        setLoading(false)
      })
      .catch((error) => {

        setLoading(false)
      })
  }, [is_module_active])

  if (loading) {

    return <div>Loading...</div>;
  }

  return (
    <>
      {!is_module_active ? (
        <div className="nfd-container-upsell" >
          <InstallActivatePluginButton methods={methods} constants={constants} setModuleStatus={setModuleStatus} />
          <FeatureUpsell
            cardText={__('Enjoy with Jetpack Boost module', 'wp-module-performance')}
            cardLink='https://wordpress.org/plugins/jetpack-boost/'
          >
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