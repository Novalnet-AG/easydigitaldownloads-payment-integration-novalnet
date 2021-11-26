/**
 * Novalnet Function action
 *
 * @category   Novalnet function action
 * @package    edd-novalnet-gateway
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

function day_blur_action ( e , payment ) {

			var date, updated_date;
			updated_date = date = jQuery( '#' + payment + '_day' ).val();
	if ( date != '0' && date != '' && date.length < 2 ) {
		updated_date = "0" + date;
	} else if ( date == '0' ) {
		updated_date = date.replace( '0', '01' );
	} else if ( date == '00' ) {
		updated_date = date.replace( '00', '01' );
	}
			jQuery( '#' + payment + '_day' ).val( updated_date );
}

/* Check for year validation */
function  year_validation ( e , payment ) {

			var current_date = new Date();
			var max_year     = current_date.getFullYear() - 18;
			var min_year     = current_date.getFullYear() - 91;
			var year_val     = jQuery( '#' + payment + '_year' ).val();
			var year_len     = year_val.length;
			let maximum_year = parseInt( max_year.toString().substring( 0 ,year_len ) );
			let minimum_year = parseInt( min_year.toString().substring( 0 ,year_len ) );
			let user_val     = year_val.substring( 0, year_len );
	if ( e.keyCode != 8 || e.keyCode != 46 ) {
		if ( user_val > maximum_year || user_val < minimum_year || isNaN( user_val ) ) {
			jQuery( '#' + payment + '_year' ).val( year_val.substring( 0, year_len - 1 ) );
			e.preventDefault();
			e.stopImmediatePropagation();
			return false;
		}
	}
}


/* Check for valid date */
function allow_date( event ) {

	var cursor     = event.target.selectionStart;
	var keycode    = ( 'which' in event ) ? event.which : event.keyCode,
		reg        = /^(?:[0-9]+$)/;
	var id_val     = event.target;
	var payment_id = jQuery( id_val ).attr( 'id' );
	var payment;
	if ( payment_id == 'novalnet_sepa_day' || payment_id == 'novalnet_sepa_month' || payment_id == 'novalnet_sepa_year' ) {
		payment = payment_id.match( /novalnet_sepa/g );
	} else if ( payment_id == 'novalnet_invoice_day' || payment_id == 'novalnet_invoice_month' || payment_id == 'novalnet_invoice_year' ) {
		payment = payment_id.match( /novalnet_invoice/g );
	}

	var current_date = new Date();
	var max_year     = current_date.getFullYear() - 18;
	var min_year     = current_date.getFullYear() - 91;
	var array_month  = [ "01", "02", "03", "04", "05", "06","07", "08", "09", "10", "11", "12" ];

	monthAutocomplete( document.getElementById( payment + "_month" ), array_month );

	var year_range = [];

	for ( var year = max_year; year >= min_year; year-- ) {
		year_range.push( '' + year + '' );
	}

	yearAutocomplete( document.getElementById( payment + "_year" ), year_range );

	jQuery( "#" + payment + "_day" ).on(
		"keypress textInput",
		function ( e ) {

			var cur_val = e.target.selectionStart;
			var keyCode = e.which || e.originalEvent.data.charCodeAt( 0 );
			var expr    = String.fromCharCode( keyCode );
			day_val     = jQuery( "#" + payment + "_day" ).val();

			if ( day_val.length == 1 ) {
				if ( expr > 2 && day_val.charAt( 0 ) > 2 && cur_val == 0 ) {
					reg = /^[0-2]$/;
					return ( reg.test( String.fromCharCode( keycode ) ) || 0 === keycode || 8 === keycode );
				} else if ( ( day_val.charAt( 0 ) >= 4 && expr > 3 && ( cur_val == 0 || cur_val == 1 ) ) || (day_val.charAt( 0 ) == 3 && expr > 1 && cur_val == 1 ) ) {
					return false;
				} else if ( ( cur_val == 0 && day_val.charAt( 0 ) == 1 && expr > 3 ) || ( cur_val == 0 && day_val.charAt( 0 ) == 2 && expr > 2 ) || ( day_val.length == 1 && cur_val == 1 && day_val > 3 ) ) {
					return false;
				} else if (  day_val.charAt( 0 ) == 0 && expr > 3 && cur_val == 0 ) {
					return false;
				}
			}
		}
	);

	jQuery( "#" + payment + "_day" ).on(
		'blur',
		function ( e ) {
			day_blur_action( e , payment );
		}
	);

	jQuery( "#" + payment + "_year" ).on(
		"input",
		function ( e ) {
			year_validation( e , payment );
		}
	);

	jQuery( "#" + payment + "_month" ).on(
		'blur',
		function ( e ) {
			var month = this.value;
			if ( ( month.length == 1 && month == '0' ) || ( month.length == 2 && month == '00' ) ) {
				jQuery( "#" + payment + "_month" ).val( '01' );
			} else if ( month.length == 1 ) {
				jQuery( "#" + payment + "_month" ).val( '0' + month );
			}
		}
	);

	jQuery( "#" + payment + "_month" ).keypress(
		function ( event ) {
			var keycode = ( 'which' in event ) ? event.which : event.keyCode;
			var expr    = String.fromCharCode( keycode );
			var month   = jQuery( "#" + payment + "_month" ).val();
			var cursor  = event.target.selectionStart;
			if ( jQuery( "#" + payment + "_month" ).val() > 0 ) {
				  reg = /^(?:[0-2]+$)/;
			} else {
				reg = /^(?:[0-9]+$)/;
			}

			if ( (  month > 2 && month.length == 1 && cursor == 0 && expr > 0 ) || ( ( month == 2 || month == 1 || month == 0 ) && month.length == 1 && cursor == 0 && expr > 1   ) ) {
				return false;
			} else if ( (  month >= 2 && month.length == 1 && cursor == 1 && expr >= 0 )  ) {
				return false;
			}
			return ( reg.test( String.fromCharCode( keycode ) ) || 0 === keycode || 8 === keycode );
		}
	);

	return ( reg.test( String.fromCharCode( keycode ) ) || 0 === keycode || 8 === keycode );
}

/* Check for Month list Validation */
function monthAutocomplete( input_val , array_month ) {

			  var currentFocus;
			  var payment = input_val.id;

			input_val.addEventListener(
				"input",
				function ( e ) {

					var a, b, i, val = this.value;

					closeAllLists( input_val );
					if ( ! val || val.length < 1 ) {
							return false;
					}
					currentFocus = -1;

					a = document.createElement( "div" );
					a.setAttribute( "id", this.id + "autocomplete-month-list" );
					a.setAttribute( "class", "autocomplete-items" );
					a.style.width           = "123px";
					a.style.margin          = "0 auto";
					a.style.border          = "2px solid #d4d0ba";
					a.style.marginLeft      = "110px";
					a.style.position        = "absolute";
					a.style.backgroundColor = "#fff";

					this.parentNode.appendChild( a );
					var count = 0;
					var month_length = array_month.length;
					for ( i = 0; i < month_length; i++ ) {
						var regex = new RegExp( val, 'g' );
						if ( array_month[i].match( regex ) ) {
							if ( count == 12 ) {
								break;
							}
							b            = document.createElement( "div" );
							b.innerHTML  = array_month[i].replace( val,"<strong>" + val + "</strong>" );
							b.innerHTML += "<input type='hidden' class='month_active' value='" + array_month[i] + "'>";
							b.addEventListener(
								"click",
								function ( e ) {
									input_val.value = this.getElementsByTagName( "input" )[0].value;
									closeAllLists( input_val );
								}
							);
							b.onmouseover    = function() {
								this.style.backgroundColor = "#d4d0ba";
							}
								b.onmouseout = function() {
									this.style.backgroundColor = "#fff";
								}
								a.appendChild( b );
								count++;
						}
					}

				}
			);

		input_val.addEventListener(
			"keydown",
			function ( e ) {

				var x = document.getElementById( this.id + "autocomplete-month-list" );
				if (x) {
					x = x.getElementsByTagName( "div" );
				}
				if ( e.keyCode == 40 ) {
					currentFocus++;
					addActiveValue( x );
				} else if ( e.keyCode == 38 ) {
					currentFocus--;
					addActiveValue( x );
				} else if ( e.keyCode == 13 ) {
					e.preventDefault();
					if ( currentFocus > -1 ) {
						if (x) {
							x[currentFocus].click();
						}
					}
				}
			}
		);

	function addActiveValue( x ) {
		if ( ! x) {
			return false;
		}
		removeActiveValue( x );
		if (  isNaN( currentFocus ) ) {
				currentFocus = 0;
		}
		if ( currentFocus >= x.length ) {
			currentFocus = 0;
		}
		if ( currentFocus < 0 ) {
			currentFocus = ( x.length - 1 );
		}
			x[currentFocus].classList.add( "autocomplete-active" );
			var elements = jQuery( x[currentFocus] );
			jQuery( '#' + payment ).val( jQuery( '.month_active', elements ).val() );
	}

		jQuery( "#" + payment ).on(
			'click',
			function ( e ) {
				closeAllLists( e.target );
			}
		);

}

function closeAllLists ( input_val, elmnt ) {
	var x = document.getElementsByClassName( "autocomplete-items" );
	for ( var i = 0; i < x.length; i++ ) {
		if ( elmnt != x[i] && elmnt != input_val ) {
				x[i].parentNode.removeChild( x[i] );
		}
	}
}

function removeActiveValue( x ) {
	for ( var i = 0; i < x.length; i++ ) {
		x[i].classList.remove( "autocomplete-active" );
	}
}

/* Check for Year list Validation */
function yearAutocomplete ( input_val, array_year ) {

		var currentFocus;
		var payment = input_val.id;

		input_val.addEventListener(
			"input",
			function ( e ) {

				var a, b, i, val = this.value;

				closeAllLists( input_val );
				if ( ! val || val.length < 2 || val.length > 3) {
						return false;
				}
				currentFocus = -1;

				a = document.createElement( "div" );
				a.setAttribute( "id", this.id + "autocomplete-list" );
				a.setAttribute( "class", "autocomplete-items" );
				a.style.width           = "10%";
				a.style.float           = "right";
				a.style.border          = "2px solid #d4d0ba";
				a.style.marginLeft      = "236px";
				a.style.position        = "absolute";
				a.style.backgroundColor = "#fff";

				this.parentNode.appendChild( a );
				var count = 1;
				for ( i = 0; i < array_year.length; i++ ) {
					  var regex = new RegExp( val, 'g' );
					if ( array_year[i].match( regex ) ) {
						if ( count == 11 ) {
							break;
						}
						b            = document.createElement( "div" );
						b.innerHTML  = array_year[i].replace( val,"<strong>" + val + "</strong>" );
						b.innerHTML += "<input type='hidden' class='year_active' value='" + array_year[i] + "'>";
						b.addEventListener(
							"click",
							function ( e ) {
								input_val.value = this.getElementsByTagName( "input" )[0].value;
								closeAllLists( input_val );
							}
						);
						b.onmouseover = function() { this.style.backgroundColor = "#d4d0ba"; }
						b.onmouseout  = function() { this.style.backgroundColor = "#fff"; }
						a.appendChild( b );
						count++;
					}
				}
			}
		);

		input_val.addEventListener(
			"keydown",
			function ( e ) {
				var x = document.getElementById( this.id + "autocomplete-list" );
				if (x) {
					x = x.getElementsByTagName( "div" );
				}
				if ( e.keyCode == 40 ) {
					  currentFocus++;
					  addActiveValue( x );
				} else if ( e.keyCode == 38 ) {
					currentFocus--;
					addActiveValue( x );
				} else if ( e.keyCode == 13 ) {
					e.preventDefault();
					if ( currentFocus > -1 ) {
						if (x) {
							x[currentFocus].click();
						}
					}
				}
			}
		);

	function addActiveValue( x ) {
		if ( ! x) {
			return false;
		}
		removeActiveValue( x );
		if ( currentFocus >= x.length ) {
			currentFocus = 0;
		}
		if ( currentFocus < 0 ) {
			currentFocus = ( x.length - 1 );
		}
		x[currentFocus].classList.add( "autocomplete-active" );
		var elements = jQuery( x[currentFocus] );
		jQuery( '#' + payment ).val( jQuery( '.year_active', elements ).val() );
	}

	jQuery( "#" + payment ).on(
		'click',
		function ( e ) {
			closeAllLists( e.target );
		}
	);

}
