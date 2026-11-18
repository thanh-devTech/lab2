let autocomplete;
let address1Field;
let cityField;
let stateField;
let postalField;

function initAutocomplete() {
	address1Field = document.querySelector("#pa_address");
	cityField = document.querySelector("#pa_city");
	stateField = document.querySelector("#papa_state");
	postalField = document.querySelector("#pa_zip");
	// Create the autocomplete object, restricting the search predictions to
	// addresses in the US and Canada.
	autocomplete = new google.maps.places.Autocomplete(address1Field, {
		componentRestrictions: { country: ["us"] },
		fields: ["address_components", "geometry"],
		types: ["address"],
	});
	//address1Field.focus();
	// When the user selects an address from the drop-down, populate the
	// address fields in the form.
	autocomplete.addListener("place_changed", fillInAddress);
}

function fillInAddress() {
	// Get the place details from the autocomplete object.
	const place = autocomplete.getPlace();
	let address1 = "";
	let postcode = "";
	let state = '';
	let city = '';

	// Get each component of the address from the place details,
	// and then fill-in the corresponding field on the form.
	// place.address_components are google.maps.GeocoderAddressComponent objects
	// which are documented at http://goo.gle/3l5i5Mr
	for (const component of place.address_components) {
		// @ts-ignore remove once typings fixed
		const componentType = component.types[0];
		console.log(componentType);
		console.log(component);

		switch (componentType) {
			case "street_number": {
				address1 = `${component.long_name} ${address1}`;
				break;
			}

			case "route": {
				address1 += component.short_name;
				break;
			}

			case "postal_code": {
				postcode = `${component.long_name}${postcode}`;
				break;
			}

			case "postal_code_suffix": {
				postcode = `${postcode}-${component.long_name}`;
				break;
			}
			case "administrative_area_level_1": {
				state = component.short_name;
				break;
			}

			case 'locality':{
				city = component.long_name;
				break;
			}
		}
	}

	address1Field.value = address1;
	postalField.value = postcode;
	cityField.value = city;
	stateField.value = state;
	jQuery(stateField).change();
}

window.initAutocomplete = initAutocomplete;