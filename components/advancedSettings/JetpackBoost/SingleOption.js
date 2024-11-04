import React, { useState, useEffect, useRef } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { ToggleField, Textarea, Notifications } from '@newfold/ui-component-library';

const SingleOption = ( {params, isChild, methods, constants } ) =>  {

  const [ optionDetails, setOptionDetails ] = useState({
    id: params.id,
    label: params.label,
    description: params.description,
    value: params.value ? String(params.value) : '',
    type: params.type,
    externalLink: params.externalLink,
    children: params.children
  });


  const [ isShown, setIsShown ] = useState( false );

  const debounceTimeout = useRef(null); // Mantiene il timeout tra i render


  const handleChangeOption = ( value, id ) => {

    if( typeof value === 'object' ){
      value = value.target.value;
    }

    setOptionDetails({ ...optionDetails, value: value });

    // Cancella il timeout precedente se l'utente digita di nuovo
    if (debounceTimeout.current) {
      clearTimeout(debounceTimeout.current);
    }

    // Imposta un nuovo timeout di 2 secondi
    debounceTimeout.current = setTimeout(() => {
      apiFetch({
        path: 'newfold-performance/v1/jetpack/set_options',
        method: 'POST',
        data: {
          field: {
            id: id,
            value: value
          },
        }
      }).then((response) => {
        methods.makeNotice(
          "cache-level-change-notice",
          constants.text.jetpackOptionSaved,
          '',
          "success",
          5000
        );
      }).catch((error) => {

      });
    }, 1000);


  }

  const handleTextInputChange = ( value, id ) => {

  };


  const displayOption = ( params ) => {
    switch( params.type ){
      case 'toggle':
        return (
          <>
            <ToggleField
              id = { params.id }
              label = { params.label }
              description = { params.description }
              checked={params.value ? true: false}
              onChange={ ( value ) => { handleChangeOption( value, params.id ) } }
            />
            { params.externalLink ? <p style={{ textAlign: "right", marginBottom: "30px" }}>{ constants.text.jetpackBoostDicoverMore } <a href={`${window.location.origin}/wp-admin/admin.php?page=jetpack-boost`}> { __( 'here', 'newfold-module-performance' ) } </a></p> : '' }
          </>
        );

      case 'textarea':
        return (
          <>
            <p className="field-label">{params.label}</p>
            <Textarea
              id = { params.id }
              description = { params.description }
              value = { params.value ?? '' }
              onChange={ ( value ) => { handleChangeOption( value, params.id ) } }
            />
          </>

        );
      default:
        return null;
    }
  }

  return (
    <>
      { isChild && (
        <div className="child-field">
          <div className="wrap-button" style={{ textAlign: 'right' }}>
            <button onClick={ () => setIsShown(!isShown) }>
              { isShown ? constants.text.jetpackBoostShowLess : constants.text.jetpackBoostShowMore }
            </button>
          </div>
          { isShown && displayOption( optionDetails ) }
        </div>
      )}
      { ! isChild && displayOption( optionDetails ) }

    </>
  );
}

export default SingleOption;