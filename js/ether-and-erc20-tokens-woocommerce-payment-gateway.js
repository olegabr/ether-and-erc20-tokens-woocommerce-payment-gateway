var EPG_STEP = {
    deposit: 0,
    payment: 1,
    result: 2
};

// http://locutus.io/php/math/round/
function epg_round (value, precision, mode) {
  //  discuss at: http://locutus.io/php/round/
  // original by: Philip Peterson
  //  revised by: Onno Marsman (https://twitter.com/onnomarsman)
  //  revised by: T.Wild
  //  revised by: Rafał Kukawski (http://blog.kukawski.pl)
  //    input by: Greenseed
  //    input by: meo
  //    input by: William
  //    input by: Josep Sanz (http://www.ws3.es/)
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  //      note 1: Great work. Ideas for improvement:
  //      note 1: - code more compliant with developer guidelines
  //      note 1: - for implementing PHP constant arguments look at
  //      note 1: the pathinfo() function, it offers the greatest
  //      note 1: flexibility & compatibility possible
  //   example 1: round(1241757, -3)
  //   returns 1: 1242000
  //   example 2: round(3.6)
  //   returns 2: 4
  //   example 3: round(2.835, 2)
  //   returns 3: 2.84
  //   example 4: round(1.1749999999999, 2)
  //   returns 4: 1.17
  //   example 5: round(58551.799999999996, 2)
  //   returns 5: 58551.8

  var m, f, isHalf, sgn // helper variables
  // making sure precision is integer
  precision |= 0
  m = Math.pow(10, precision)
  value *= m
  // sign of the number
  sgn = (value > 0) | -(value < 0)
  isHalf = value % 1 === 0.5 * sgn
  f = Math.floor(value)

  if (isHalf) {
    switch (mode) {
      case 'PHP_ROUND_HALF_DOWN':
      // rounds .5 toward zero
        value = f + (sgn < 0)
        break
      case 'PHP_ROUND_HALF_EVEN':
      // rouds .5 towards the next even integer
        value = f + (f % 2 * sgn)
        break
      case 'PHP_ROUND_HALF_ODD':
      // rounds .5 towards the next odd integer
        value = f + !(f % 2)
        break
      default:
      // rounds .5 away from zero
        value = f + (sgn > 0)
    }
  }

  return (isHalf ? value : Math.round(value)) / m
}

function epg_tokenChange(event) {
	event.preventDefault();
    if (window.epg.is_wizard_initialised) {
        var token = jQuery('#epg-token').val();
        if (token !== 'ETH') {
            jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.deposit);
        }
    }
    setTimeout(function() {
        epg_fill_payment_info(function(error, result){
            if (error) {
                epg_alert(error);
                return;
            }
        });
    }, 1);
}

function epg_fill_payment_info(cb) {
    if ('undefined' === typeof cb) {
        cb = function(){};
    }
	var token = jQuery('#epg-token').val();
	if (token === 'ETH') {
		epg_fill_ether_payment_info(function(error, result) {
            if ('undefined' === typeof window.epg['epg-ether-advanced-details-opened'] && 
                !(typeof window !== 'undefined' && typeof window.web3 !== 'undefined')
            ) {
                jQuery('#epg-ether-advanced-details-button').click();
                jQuery('#epg-ether-advanced-details-button').parent().hide();
                window.epg['epg-ether-advanced-details-opened'] = true;
            }
            cb.call(null, error, result);
        });
	} else {
		epg_fill_token_payment_info(token, function(error, result) {
            if ('undefined' === typeof window.epg['epg-token-advanced-details-step1-opened'] && 
                !(typeof window !== 'undefined' && typeof window.web3 !== 'undefined')
            ) {
                jQuery('#epg-token-advanced-details-step1-button').click();
                jQuery('#epg-token-advanced-details-step1-button').parent().hide();
                window.epg['epg-token-advanced-details-step1-opened'] = true;
            }
            change_epg_gateway_address();
            change_epg_data_value();
            cb.call(null, error, result);
        });
	}
}

function epg_alert(msg) {
	var timeoutId = setTimeout(function() {
		clearTimeout(timeoutId);
		alert(msg);
	}, 1);
}
function epg_getTokenInfoBySymbol(tokenSymbol) {
	if ("" === window.epg.tokens_supported) {
		return null;
	}
	var tokenStrings = window.epg.tokens_supported.split(",");
	for (var i = 0; i < tokenStrings.length; i++) {
		var tokenString = tokenStrings[i];
		var tokenParts = tokenString.split(":");
		if (tokenParts.length !== 3) {
			continue;
		}
		var symbol  = tokenParts[0];
		var address = tokenParts[1];
		var rate    = tokenParts[2];
		if (symbol.toLowerCase() === tokenSymbol.toLowerCase()) {
			return {
				symbol:  symbol,
				address: address,
				rate:    rate
			};
		}
	}
	return null;
}

function epg_getTokenInfoByAddress(tokenAddress) {
	if ("" === window.epg.tokens_supported) {
		return null;
	}
	var tokenStrings = window.epg.tokens_supported.split(",");
	for (var i = 0; i < tokenStrings.length; i++) {
		var tokenString = tokenStrings[i];
		var tokenParts = tokenString.split(":");
		if (tokenParts.length !== 3) {
			continue;
		}
		var symbol  = tokenParts[0];
		var address = tokenParts[1];
		var rate    = tokenParts[2];
		if (address.toLowerCase() === tokenAddress.toLowerCase()) {
			return {
				symbol:  symbol,
				address: address,
				rate:    rate
			};
		}
	}
	return null;
}

function epg_calc_with_token_markup(value) {
    return parseFloat(value) * (100 + parseFloat(window.epg.markup_percent_token)) / (100 + parseFloat(window.epg.markup_percent));
}

// fill the epg-payment-info div
function epg_fill_token_payment_info(token, cb) {
    if ('undefined' === typeof cb) {
        cb = function(){};
    }
    epg_initWizard(function(error, result) {
        if (error) {
            cb.call(null, error, result);
            return;
        }
        jQuery('#epg-ether-payment').addClass('hidden');
        jQuery('#epg-ether-payment').attr('hidden', ' hidden');

        jQuery('#epg-data-value-group').removeClass('hidden');
        jQuery('#epg-data-value-group').removeAttr('hidden');

        jQuery('#epg-balance-group').addClass('hidden');
        jQuery('#epg-balance-group').attr('hidden', ' hidden');
        var tokenInfo = epg_getTokenInfoBySymbol(token);
        if (tokenInfo) {
            jQuery('#epg-gateway-address').val(tokenInfo.address);
            var rate = epg_getTokenRate(tokenInfo.address);
            if (null === rate) {
                console.log("Failed to get token rate");
                jQuery('#epg-amount').val('');
                jQuery('#epg-amount2').val('');
                cb.call(null, "Failed to get token rate", null);
                return;
            }
            epg_get_token_decimals(tokenInfo.address, function(error, decimals) {
                if (error) {
                    console.log(error);
                    jQuery('#epg-amount').val('');
                    jQuery('#epg-amount2').val('');
                    cb.call(null, error, null);
                    return;
                }
                if (null === decimals) {
                    console.log("Failed to obtain ERC20 token decimals value");
                    jQuery('#epg-amount').val('');
                    jQuery('#epg-amount2').val('');
                    cb.call(null, "Failed to obtain ERC20 token decimals value", null);
                    return;
                }
                var rate = epg_getTokenRate(tokenInfo.address);
                if (null === rate) {
                    console.log("Failed to obtain token rate");
                    jQuery('#epg-amount').val('');
                    jQuery('#epg-amount2').val('');
                    cb.call(null, "Failed to obtain token rate", null);
                    return;
                }
                var tokenAmount = epg_calc_with_token_markup(window.epg.eth_value) / rate;
                // cut decimals beyond the token supported maximum
                var tokenAmount2 = Math.ceil(tokenAmount * Math.pow(10, decimals.toNumber()));
                tokenAmount = tokenAmount2 / Math.pow(10, decimals.toNumber());

                jQuery('#epg-amount').val(tokenAmount);
                jQuery('#epg-amount2').val(tokenAmount);
                // always zero here!
                jQuery('#epg-value').val(0);

                epg_token_approve_getData(tokenInfo.address, tokenAmount, function(error, data) {
                    if (error) {
                        console.log(error);
                        jQuery('#epg-data-value').text('');
                        cb.call(null, error, null);
                        return;
                    }
                    jQuery('#epg-data-value').text(data);
                    epg_token_check_deposit(tokenInfo.address, tokenAmount, function(error, is_deposited_already) {
                        if (error) {
                            console.log(error);
                            epg_alert(error);
                            cb.call(null, error, null);
                            return;
                        }
                        if (true === is_deposited_already) {
                            jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.payment);
                            var timeoutId = setTimeout(function() {
                                clearTimeout(timeoutId);
                                jQuery('#epg-button-next').removeClass('disabled');
                            }, 1);
                            jQuery('#epg-payment-deposit-made-message-wrapper').removeClass('hidden');
                            jQuery('#epg-payment-deposit-made-message-wrapper').removeAttr('hidden');
                            jQuery('#epg-payment-incomplete-message-wrapper').addClass('hidden');
                            jQuery('#epg-payment-incomplete-message-wrapper').attr('hidden', ' hidden');
                            jQuery('#epg-payment-success-message-wrapper').addClass('hidden');
                            jQuery('#epg-payment-success-message-wrapper').attr('hidden', ' hidden');
                            jQuery('#epg-ether-payment').addClass('hidden');
                            jQuery('#epg-ether-payment').attr('hidden', ' hidden');
                        } else {
                            jQuery('#epg-payment-deposit-made-message-wrapper').addClass('hidden');
                            jQuery('#epg-payment-deposit-made-message-wrapper').attr('hidden', 'hidden');
                            jQuery('#epg-payment-incomplete-message-wrapper').removeClass('hidden');
                            jQuery('#epg-payment-incomplete-message-wrapper').removeAttr('hidden', ' hidden');
                        }
                        cb.call(null, null, null);
                    });
                });
            });
        } else {
            console.log("failed to find token info");
            jQuery('#epg-data-value').text('');
            jQuery('#epg-amount').val('');
            jQuery('#epg-amount2').val('');
            jQuery('#epg-value').val('');
            cb.call(null, "failed to find token info", null);
        }
    });
}

// fill the epg-eth-payment-info div
function epg_fill_ether_payment_info(cb) {
    if ('undefined' === typeof cb) {
        cb = function(){};
    }
    jQuery('#epg-ether-payment').removeClass('hidden');
    jQuery('#epg-ether-payment').removeAttr('hidden');
    
    jQuery('#epg-payment-deposit-made-message-wrapper').addClass('hidden');
    jQuery('#epg-payment-deposit-made-message-wrapper').attr('hidden', 'hidden');
    jQuery('#epg-payment-incomplete-message-wrapper').removeClass('hidden');
    jQuery('#epg-payment-incomplete-message-wrapper').removeAttr('hidden', ' hidden');
    
    jQuery('#rootwizard').addClass('hidden');
    jQuery('#rootwizard').attr('hidden', ' hidden');
    
    jQuery('#rootwizard-help-info').addClass('hidden');
    jQuery('#rootwizard-help-info').attr('hidden', ' hidden');
//	var value = parseFloat(window.epg.eth_value);
//	jQuery('#epg-ether-data-value').val(value);
//    jQuery('#epg-ether-gateway-address').val(window.epg.gateway_address);
    cb.call(null, null, null);
}

function epg_get_step_number() {
	var token = jQuery('#epg-token').val();
	if (token === 'ETH') {
        return null;
    }
	if (jQuery('#epg-payment-step1').hasClass('active')) {
		return EPG_STEP.deposit;
	}
	if (jQuery('#epg-payment-step2').hasClass('active')) {
		return EPG_STEP.payment;
	}
	console.log("EPG INTERNAL ERROR: unknown step!");
	return null;
}

function epg_switch_to_step2(cb) {
    jQuery('#epg-button-next').text(window.epg.str_pay_button_text);
    jQuery('#epg-gateway-address').val(window.epg.gateway_address);
    jQuery('#epg-gateway-address-step2').val(window.epg.gateway_address);
	var token = jQuery('#epg-token').val();
	if (token === 'ETH') {
        epg_payEth_getData(function(error, data) {
            if (error) {
                console.log(error);
                jQuery('#epg-data-value-step2').text('');
                cb.call(null, error, null);
                return;
            }
            jQuery('#epg-data-value-step2').text(data);
            cb.call(null, null, data);
        });
	} else {
        var tokenInfo = epg_getTokenInfoBySymbol(token);
        if (tokenInfo) {
            epg_payToken_getData(tokenInfo.address, function(error, data) {
                if (error) {
                    console.log(error);
                    jQuery('#epg-data-value-step2').text('');
                    cb.call(null, error, null);
                    return;
                }
                jQuery('#epg-data-value-step2').text(data);
                cb.call(null, null, data);
            });
        } else {
            console.log("Failed to obtain token info");
            cb.call(null, window.epg.str_pay_token_failure, null);
        }
	}
}

function epg_switch_to_step1(cb) {
	epg_fill_payment_info(function(error, result){
        if (error) {
            cb.call(null, error, result);
            return;
        }
        jQuery('#epg-button-next').text(window.epg.str_make_deposit_button_text);
        cb.call(null, error, result);
    });
}

// wrap user accounts source for non-metamask case
function epg_getUserAccounts(callback) {
	// this function is used if no Metamask is defined
	var _fn = function(callback) {
        callback.call(null, window.epg.str_download_metamask, []);
	};
	var _eth = null;
	if ('undefined' !== typeof window.epg['web3metamask']) {
		_fn = window.epg.web3metamask.eth.getAccounts;
		_eth = window.epg.web3metamask.eth;
	}
	_fn.call(_eth, function(err, accounts) {
		callback.call(null, err, accounts);
	});
}

 // https://ethereum.stackexchange.com/a/2830
 // @method epg_awaitBlockConsensus
 // @param txhash is the transaction hash from when you submitted the transaction
 // @param blockCount is the number of blocks to wait for.
 // @param timeout in seconds 
 // @param callback - callback(error, transaction_receipt) 
 //
 function epg_awaitBlockConsensus(txhash, blockCount, timeout, callback) {
   var txWeb3 = window.epg.web3;
   var startBlock = Number.MAX_SAFE_INTEGER;
   var interval;
   var stateEnum = { start: 1, mined: 2, awaited: 3, confirmed: 4, unconfirmed: 5 };
   var savedTxInfo;
   var attempts = 0;

   var pollState = stateEnum.start;

   var poll = function() {
     if (pollState === stateEnum.start) {
       txWeb3.eth.getTransaction(txhash, function(e, txInfo) {
         if (e || txInfo === null) {
           return; // XXX silently drop errors
         }
         if (txInfo.blockHash !== null) {
           startBlock = txInfo.blockNumber;
           savedTxInfo = txInfo;
           console.log("mined");
           pollState = stateEnum.mined;
         }
       });
     }
     else if (pollState === stateEnum.mined) {
         txWeb3.eth.getBlockNumber(function (e, blockNum) {
           if (e) {
             return; // XXX silently drop errors
           }
           console.log("blockNum: ", blockNum);
           if (blockNum >= (blockCount + startBlock)) {
             pollState = stateEnum.awaited;
           }
         });
     }
    else if (pollState === stateEnum.awaited) {
         txWeb3.eth.getTransactionReceipt(txhash, function(e, receipt) {
           if (e || receipt === null) {
             return; // XXX silently drop errors.  TBD callback error?
           }
           // confirm we didn't run out of gas
           // XXX this is where we should be checking a plurality of nodes.  TBD
           clearInterval(interval);
           if (receipt.gasUsed >= savedTxInfo.gas) {
             pollState = stateEnum.unconfirmed;
             callback(new Error("we ran out of gas, not confirmed!"), null);
           } else {
             pollState = stateEnum.confirmed;
             callback(null, receipt);
           }
       });
     } else {
	   callback(new Error("We should never get here, illegal state: " + pollState), null);
	   return;
     }

     // note assuming poll interval is 1 second
     attempts++;
     if (attempts > timeout) {
       clearInterval(interval);
       pollState = stateEnum.unconfirmed;
       callback(new Error("Timed out, not confirmed"), null);
     }
   };

   interval = setInterval(poll, 1000);
   poll();
 }
 
function epg_sendTransaction_aux(to, value, data, cb) {
    if (window.epg.mm_network_mismatch) {
        cb.call(null, window.epg.str_metamask_network_mismatch, null);
        return;
    }
	epg_getUserAccounts(function(err, accounts) {

		if (err) {
			console.log(err); 
			cb.call(null, err, null);
			return;
		}

		if (0 === accounts.length) {
			console.log("Metamask account not found"); 
			cb.call(null, window.epg.str_unlock_metamask_account, null);
			return;
		}
		
		var address = accounts[0];
		var transactionObject = {
			from: address,
			to: to,
			value: value,
			gas: window.epg.gas_limit,
			gasPrice: parseFloat(window.epg.gas_price) * 1000000000,
			data: data,
			nonce: '0x00'
		};
		// init waiting icon
		epg_show_wait_icon();
		window.epg.web3.eth.getTransactionCount(address, function(err, res) {
			if (err) {
				epg_hide_wait_icon();
				console.log(err);
				console.log("Network error. Check your infuraApiKey settings.");
				cb.call(null, err, null);
				return;
			}
			console.log("Current address nonce value: ", res);
			var nonce = parseInt(res);
			transactionObject.nonce = "0x" + nonce.toString(16);
			console.log(transactionObject);
			window.epg.web3metamask.eth.sendTransaction(transactionObject, function (err, transactionHash) {
				if (err) {
					epg_hide_wait_icon();
					console.log(err);
					cb.call(null, err, null);
					return;
				}
				console.log(transactionHash);
				// https://www.reddit.com/r/ethereum/comments/4eplsv/how_many_confirms_is_considered_safe_in_ethereum/d229xie/
				var blockCount = 12; // TODO: add admin setting
				var timeout = 5 * 60; // 5 minutes in seconds
				epg_awaitBlockConsensus(transactionHash, blockCount, timeout, function(err, transaction_receipt) {
					epg_hide_wait_icon();
					if (err) {
						console.log(err);
						cb.call(null, err, transactionHash);
						return;
					}
					cb.call(null, null, transactionHash);
				});
			});
		});
	});
}

function epg_sendTransaction_aux2(value, data, cb) {
	var token = jQuery('#epg-token').val();
    var to = jQuery('#epg-gateway-address').val();
    if (token === 'ETH') {
        to = jQuery('#epg-ether-gateway-address').val();
    }
    epg_sendTransaction_aux(to, value, data, function(err, transactionHash) {
        if (err) {
            console.log(err);
            if (err === window.epg.str_unlock_metamask_account) {
                cb.call(null, err, null);
                return;
            }
            if (token === 'ETH') {
                cb.call(null, window.epg.str_pay_eth_failure, null);
            } else {
                cb.call(null, window.epg.str_pay_token_failure, null);
            }
            return;
        }
        console.log("tx: ", transactionHash);
        cb.call(null, null, transactionHash);
    });
}

function epg_sendTransaction_impl(cb) {
	var token = jQuery('#epg-token').val();
    if (token === 'ETH') {
        var value = window.epg.eth_value_with_dust;
        var data = window.epg.payment_address;
        epg_sendTransaction_aux2(value, data, cb);
    } else {
        // 1. проверить баланс токена
        // 2. approve токена
        var tokenAmount = parseFloat(jQuery('#epg-amount').val());
        epg_get_token_balance(token, function(err, balance) {
            // balance is already in token units
            if (err) {
                console.log(err); 
                cb.call(null, err, null);
                return;
            }

            if (balance < tokenAmount) {
                cb.call(null, window.epg.str_pay_token_failure_insufficient_balance, null);
                return;
            }
            var value = window.epg.web3.toWei(parseFloat(jQuery('#epg-value').val()), 'ether');
            var data = jQuery('#epg-data-value').text();
            epg_sendTransaction_aux2(value, data, cb);
        });
    }
}

function epg_get_token_balance(token, cb) {
    epg_getUserAccounts(function(err, accounts) {

        if (err) {
            console.log(err); 
            cb.call(null, err, null);
            return;
        }

        if (0 === accounts.length) {
            console.log("Metamask account not found"); 
            cb.call(null, window.epg.str_unlock_metamask_account, null);
            return;
        }

        var tokenInfo = epg_getTokenInfoBySymbol(token);
        if (tokenInfo) {
            epg_get_token_decimals(tokenInfo.address, function(error, decimals) {
                if (error) {
                    console.log(error);
                    cb.call(null, window.epg.str_pay_token_failure, null);
                    return;
                }
                if (null === decimals) {
                    console.log("Failed to obtain ERC20 token decimals value");
                    cb.call(null, window.epg.str_pay_token_failure, null);
                    return;
                }
                var rate = epg_getTokenRate(tokenInfo.address);
                if (null === rate) {
                    console.log("Failed to obtain token rate");
                    cb.call(null, window.epg.str_pay_token_failure, null);
                    return;
                }
                epg_get_token_balance_by_account(tokenInfo.address, accounts[0], function(err, balance) {
                    if (err) {
                        console.log(err); 
                        cb.call(null, window.epg.str_pay_token_failure, null);
                        return;
                    }
                    console.log("Token balance: ", balance.toNumber());
                    var tokenValue = balance.toNumber() / Math.pow(10, decimals.toNumber());
                    cb.call(null, null, tokenValue);
                });
            });
        } else {
            console.log("Failed to obtain token info");
            cb.call(null, window.epg.str_pay_token_failure, null);
            return;
        }
    });
}

//function epg_sendTransaction_eth_step2(event) {
//	event.preventDefault();
//    epg_sendTransaction_eth_step2_impl();
//}
//
function epg_sendTransaction_eth_step2_impl() {
	var token = jQuery('#epg-token').val();
    var to = jQuery('#epg-gateway-address').val();
    if (token === 'ETH') {
        to = jQuery('#epg-ether-gateway-address').val();
    }
	var value = 0;
	var data = jQuery('#epg-data-value-step2').text();
	epg_sendTransaction_aux(to, value, data, function(err, transactionHash) {
		if (err) {
			console.log(err);
            if (err === window.epg.str_unlock_metamask_account) {
                return;
            }
			alert(window.epg.str_pay_token_failure);
			return;
		}
		epg_show_wait_icon();
		epg_getValuePayment(function(err, value) {
			if (err) {
				console.log(err);
                if (token === 'ETH') {
    				epg_alert(window.epg.str_pay_eth_failure);
                } else {
    				epg_alert(window.epg.str_pay_token_failure);
                }
    			epg_hide_wait_icon();
				return;
			}
            if (token === 'ETH') {
    			epg_hide_wait_icon();
                var etherValue = value.toNumber() / window.epg.web3.toWei(1, 'ether');
                if (epg_round (etherValue, 5, "PHP_ROUND_HALF_UP") < parseFloat(window.epg.eth_value)) {
                    console.log("Low value: ", etherValue, "ETH");
                    epg_alert(window.epg.str_pay_eth_failure);
                    return;
                }
            } else {
                var tokenInfo = epg_getTokenInfoBySymbol(token);
                if (tokenInfo) {
                    epg_get_token_decimals(tokenInfo.address, function(error, decimals) {
            			epg_hide_wait_icon();
                        if (error) {
                            console.log(error);
                            epg_alert(window.epg.str_pay_token_failure);
                            return;
                        }
                        if (null === decimals) {
                            console.log("Failed to obtain ERC20 token decimals value");
                            epg_alert(window.epg.str_pay_token_failure);
                            return;
                        }

                        var tokenAmount = parseFloat(jQuery('#epg-amount').val());
                        var tokenValue = value.toNumber() / Math.pow(10, decimals.toNumber());
                        if (tokenValue < tokenAmount) {
                            console.log("Low value: ", tokenValue, token);
                            epg_alert(window.epg.str_pay_token_failure);
                            return;
                        }
                    });
                } else {
                    epg_hide_wait_icon();
                }
            }
			epg_hide_wait_icon(EPG_STEP.result);
//            epg_switch_to_step3();
            setTimeout(function() {
                location.reload();
            }, 1);
		});
	});
}

function epg_show_wait_icon() {
    jQuery('#epg-spinner').addClass('is-active');
    jQuery('#epg-alert').removeClass('hidden');
    jQuery('#epg-alert').removeAttr('hidden');
    
    jQuery('#epg-ether-spinner').addClass('is-active');
    jQuery('#epg-ether-alert').removeClass('hidden');
    jQuery('#epg-ether-alert').removeAttr('hidden');
    
    jQuery('#epg-token').attr('disabled', 'disabled');

	switch(epg_get_step_number()) {
		case EPG_STEP.deposit :
			break;
		case EPG_STEP.payment :
			break;
		default :
			break;
	}
}

function epg_hide_wait_icon(step) {
    if ('undefined' === typeof step) {
        step = epg_get_step_number();
    }
    jQuery('#epg-spinner').removeClass('is-active');
    jQuery('#epg-alert').addClass('hidden');
    jQuery('#epg-alert').attr('hidden', ' hidden');
    
    jQuery('#epg-ether-spinner').removeClass('is-active');
    jQuery('#epg-ether-alert').addClass('hidden');
    jQuery('#epg-ether-alert').attr('hidden', ' hidden');
    
    jQuery('#epg-token').removeAttr('disabled');

	switch(step) {
		case EPG_STEP.deposit :
			break;
		case EPG_STEP.payment :
			break;
		default :
			break;
	}
}

function epg_getBalanceEth(cb) {
	epg_getUserAccounts(function(err, accounts) {

		if (err) {
			console.log(err); 
			cb.call(null, err, null);
			return;
		}

		if (0 === accounts.length) {
			console.log("Metamask account not found"); 
			cb.call(null, window.epg.str_unlock_metamask_account, null);
			return;
		}
		
		var contract = epg_get_gateway_contract();
		if (!contract) {
			console.log("Failed to obtain a gateway contract");
            var token = jQuery('#epg-token').val();
            if (token === 'ETH') {
    			cb.call(null, window.epg.str_pay_eth_failure, null);
            } else {
    			cb.call(null, window.epg.str_pay_token_failure, null);
            }
			return;
		}
		contract.getBalanceEth(function(err, res) {
			if (err) {
				console.log(err);
				cb.call(null, err, null);
				return;
			}
			console.log(res);
			cb.call(null, err, res);
		});
	});
}

// get payed value in ETH, if any
// if payment is in tokens, it is converted to ETH before return
function epg_getValuePaymentEth(cb) {
    epg_getCurrencyPayment(function(err, currencyAddress) {
        if (err) {
            console.log(err);
            cb.call(null, err, null, currencyAddress);
            return;
        }
        if (currencyAddress === "0x0000000000000000000000000000000000000000" ||
            currencyAddress === "0x") {
            // no payment was performed
            cb.call(null, null, null, currencyAddress);
            return;
        }
        epg_getValuePayment(function(err, value) {
            if (err) {
                console.log(err);
                cb.call(null, err, null, currencyAddress);
                return;
            }
            // ETH is encoded as address 0x0000000000000000000000000000000000000001
            if (currencyAddress === "0x0000000000000000000000000000000000000001") {
                cb.call(null, null, value.toNumber() / window.epg.web3.toWei(1, 'ether'), currencyAddress);
                return;
            }
            epg_get_token_decimals(currencyAddress, function(error, decimals) {
                if (error) {
                    console.log(error);
                    cb.call(null, error, null, currencyAddress);
                    return;
                }
                if (null === decimals) {
                    console.log("Failed to obtain ERC20 token decimals value");
                    cb.call(null, window.epg.str_pay_token_failure, null, currencyAddress);
                    return;
                }
                var rate = epg_getTokenRate(currencyAddress);
                if (null === rate) {
                    console.log("Failed to obtain token rate");
                    cb.call(null, window.epg.str_pay_token_failure, null, currencyAddress);
                    return;
                }

                var contract = epg_get_gateway_contract();
                if (!contract) {
                    console.log("Failed to obtain a gateway contract");
                    cb.call(null, "Failed to obtain a gateway contract", null, currencyAddress);
                    return;
                }
                var value_eth = rate * value.toNumber() / Math.pow(10, decimals.toNumber());
                cb.call(null, null, value_eth, currencyAddress);
            });
        });
    });
}

function epg_getValuePayment_v1(cb) {
	var contract = epg_get_gateway_contract_v1();
	if (!contract) {
		console.log("Failed to obtain a gateway contract");
    	var token = jQuery('#epg-token').val();
        if (token === 'ETH') {
            cb.call(null, window.epg.str_pay_eth_failure, null);
        } else {
            cb.call(null, window.epg.str_pay_token_failure, null);
        }
		return;
	}
	contract.getValuePayment(window.epg.payment_address, window.epg.order_id, function(err, res) {
		if (err) {
			console.log(err);
			cb.call(null, err, null);
			return;
		}
		console.log(res);
		cb.call(null, err, res);
	});
}

function epg_getValuePayment(cb) {
	var contract = epg_get_gateway_contract();
	if (!contract) {
		console.log("Failed to obtain a gateway contract");
    	var token = jQuery('#epg-token').val();
        if (token === 'ETH') {
            cb.call(null, window.epg.str_pay_eth_failure, null);
        } else {
            cb.call(null, window.epg.str_pay_token_failure, null);
        }
		return;
	}
	contract.getValuePayment(window.epg.payment_address, window.epg.order_id, function(err, value) {
		if (err) {
			console.log(err);
			cb.call(null, err, null);
			return;
		}
		console.log(value);
        if (value.toNumber() === 0) {
            epg_getValuePayment_v1(cb);
            return;
        }
        cb.call(null, err, value);
	});
}

function epg_getCurrencyPayment_v1(cb) {
	var contract = epg_get_gateway_contract_v1();
	if (!contract) {
		console.log("Failed to obtain a gateway contract");
        var token = jQuery('#epg-token').val();
        if (token === 'ETH') {
            cb.call(null, window.epg.str_pay_eth_failure, null);
        } else {
            cb.call(null, window.epg.str_pay_token_failure, null);
        }
		return;
	}
	contract.getCurrencyPayment(window.epg.payment_address, window.epg.order_id, function(err, res) {
		if (err) {
			console.log(err);
			cb.call(null, err, null);
			return;
		}
		console.log(res);
		cb.call(null, err, res);
	});
}

function epg_getCurrencyPayment(cb) {
	var contract = epg_get_gateway_contract();
	if (!contract) {
		console.log("Failed to obtain a gateway contract");
        var token = jQuery('#epg-token').val();
        if (token === 'ETH') {
            cb.call(null, window.epg.str_pay_eth_failure, null);
        } else {
            cb.call(null, window.epg.str_pay_token_failure, null);
        }
		return;
	}
	contract.getCurrencyPayment(window.epg.payment_address, window.epg.order_id, function(err, currencyAddress) {
		if (err) {
			console.log(err);
			cb.call(null, err, null);
			return;
		}
		console.log(currencyAddress);
        // backwards compatibility with v1
        if (currencyAddress === "0x0000000000000000000000000000000000000000" ||
            currencyAddress === "0x") {
            // no payment was performed
            epg_getCurrencyPayment_v1(cb);
            return;
        }
		cb.call(null, err, currencyAddress);
	});
}

function epg_get_gateway_contract() {
	var abi = JSON.parse(window.epg.gateway_abi);
	return window.epg.web3.eth.contract(abi).at(window.epg.gateway_address);
}

function epg_get_gateway_contract_v1() {
	var abi = JSON.parse(window.epg.gateway_abi_v1);
	return window.epg.web3.eth.contract(abi).at(window.epg.gateway_address_v1);
}

// data to call payEth method
function epg_payEth_getData(callback) {
	var value = window.epg.web3.toWei(parseFloat(window.epg.eth_value), 'ether');
    var contract = epg_get_gateway_contract();
    if (!contract) {
        console.log("Failed to obtain a gateway contract");
        callback.call(null, "Failed to obtain a gateway contract", null);
        return;
    }
    var data = contract.payEth.getData(window.epg.payment_address, window.epg.order_id, value);
    callback.call(null, null, data);
}

function epg_get_erc20_contract(tokenAddress) {
	var abi = JSON.parse(window.epg.erc20_abi);
	return window.epg.web3.eth.contract(abi).at(tokenAddress);
}

function epg_get_token_decimals(tokenAddress, callback) {
	console.log("tokenAddress: ", tokenAddress);
	var contract = epg_get_erc20_contract(tokenAddress);
	if (!contract) {
		callback.call(null, "Failed to get contract", null);
		return;
	}
	contract.decimals(callback);
}

function epg_get_token_balance_by_account(tokenAddress, account, callback) {
	var contract = epg_get_erc20_contract(tokenAddress);
	if (!contract) {
		callback.call(null, "Failed to get contract", null);
		return;
	}
	contract.balanceOf(account, callback);
}

function epg_pay_ether() {
    if ('undefined' === typeof window.epg['web3metamask']) {
        return;
    }
    if (!jQuery('#epg-ether-alert').hasClass('hidden') && !jQuery('#epg-ether-alert').is('[hidden]')) {
        // do not proceed if some task is in progress
        return false;
    }
    epg_getValuePaymentEth(function(err, value) {
        if (err) {
            console.log(err);
            epg_alert(window.epg.str_pay_eth_failure);
            return;
        }
        if (null === value || epg_round (value, 5, "PHP_ROUND_HALF_UP") < parseFloat(window.epg.eth_value_with_dust)) {
            epg_sendTransaction_impl(function(err, result) {
                if (err) {
                    console.log(err);
                    if (err === window.epg.str_unlock_metamask_account) {
                        epg_alert(window.epg.str_unlock_metamask_account);
                        return;
                    }
                    epg_alert(window.epg.str_pay_eth_failure);
                    return;
                }
                epg_alert(window.epg.str_payment_complete);
                setTimeout(function() {
                    location.reload();
                }, 1);
            });
        } else {
            epg_alert(window.epg.str_payment_complete);
        }
    });
}

function epg_getTokenRate(tokenAddress) {
	var tokenInfo = epg_getTokenInfoByAddress(tokenAddress);
	if (tokenInfo) {
		return parseFloat(tokenInfo.rate);
	}
	return null;
}

// data для вызова метода payToken
function epg_payToken_getData(tokenAddress, callback) {
	epg_get_token_decimals(tokenAddress, function(error, decimals) {
		if (error) {
			console.log(error);
			alert(window.epg.str_pay_token_failure);
			callback.call(null, error, null);
			return;
		}
		if (null === decimals) {
			console.log("Failed to obtain ERC20 token decimals value");
			callback.call(null, window.epg.str_pay_token_failure, null);
			return;
		}

        var contract = epg_get_gateway_contract();
        if (!contract) {
            console.log("Failed to obtain a gateway contract");
            callback.call(null, "Failed to obtain a gateway contract", null);
            return;
        }
        var tokenAmount = parseFloat(jQuery('#epg-amount').val());
        var tokenValue = tokenAmount * Math.pow(10, decimals.toNumber());
        console.log("payToken params:", tokenAddress, window.epg.payment_address, window.epg.order_id, tokenValue);
        var data = contract.payToken.getData(tokenAddress, window.epg.payment_address, window.epg.order_id, tokenValue);
        callback.call(null, null, data);
	});
}

// data для вызова метода token.approve
function epg_token_approve_getData(tokenAddress, tokenValue, callback) {
	epg_get_token_decimals(tokenAddress, function(error, decimals) {
		if (error) {
			console.log(error);
			callback.call(null, error, null);
			return;
		}
		if (null === decimals) {
			console.log("Failed to obtain ERC20 token decimals value");
			callback.call(null, window.epg.str_pay_token_failure, null);
			return;
		}

        var contract = epg_get_erc20_contract(tokenAddress);
        if (!contract) {
            console.log("Failed to obtain a token contract");
            callback.call(null, window.epg.str_pay_token_failure, null);
            return;
        }
        var value = Math.ceil(tokenValue * Math.pow(10, decimals.toNumber()));
        if ('undefined' !== typeof window.epg['web3metamask']) {
            epg_getUserAccounts(function(err, accounts) {

                if (err) {
                    console.log(err); 
                    callback.call(null, err, null);
                    return;
                }

                if (0 === accounts.length) {
                    console.log("Metamask account not found"); 
                    // MM account is locked. here it is equal to no MM at all
                    var data = contract.approve.getData(window.epg.gateway_address, value);
                    callback.call(null, null, data);
                    return;
                }

                var addressOwner = accounts[0];
                contract.allowance(addressOwner, window.epg.gateway_address, function(err, allowedValue) {
                    allowedValue = allowedValue.toNumber();
                    if (0 === allowedValue) {
                        var data = contract.approve.getData(window.epg.gateway_address, value);
                        callback.call(null, null, data);
                    } else {
                        // To change the approve amount you first have to reduce the addresses`
                        // allowance to zero by calling `approve(_spender, 0)` if it is not
                        // already 0 to mitigate the race condition described here:
                        // https://github.com/ethereum/EIPs/issues/20#issuecomment-263524729
                        var data = contract.approve.getData(window.epg.gateway_address, 0);
                        callback.call(null, null, data);
                    }
                });
            });
        } else {
            var data = contract.approve.getData(window.epg.gateway_address, value);
            callback.call(null, null, data);
        }
	});
}

function epg_token_check_deposit(tokenAddress, tokenValue, callback) {
	epg_get_token_decimals(tokenAddress, function(error, decimals) {
		if (error) {
			console.log(error);
			callback.call(null, error, null);
			return;
		}
		if (null === decimals) {
			console.log("Failed to obtain ERC20 token decimals value");
			callback.call(null, window.epg.str_pay_token_failure, null);
			return;
		}

        var contract = epg_get_erc20_contract(tokenAddress);
        if (!contract) {
            console.log("Failed to obtain a token contract");
            callback.call(null, window.epg.str_pay_token_failure, null);
            return;
        }
        var value = Math.ceil(tokenValue * Math.pow(10, decimals.toNumber()));
        if ('undefined' !== typeof window.epg['web3metamask']) {
            epg_getUserAccounts(function(err, accounts) {

                if (err) {
                    console.log(err); 
                    callback.call(null, err, null);
                    return;
                }

                if (0 === accounts.length) {
                    console.log("Metamask account not found"); 
                    callback.call(null, window.epg.str_unlock_metamask_account, null);
                    return;
                }

                var addressOwner = accounts[0];
                contract.allowance(addressOwner, window.epg.gateway_address, function(err, allowedValue) {
                    allowedValue = allowedValue.toNumber();
                    callback.call(null, null, allowedValue >= value);
                });
            });
        } else {
            callback.call(null, null, null);
        }
	});
}

function epg_copyAddress(e) {
	e.preventDefault();
	// copy in any case
	var $temp = jQuery("<input>");
	jQuery("body").append($temp);

	var id = jQuery(e.target).data("input-id");
	console.log("Copy from: ", id);

	var value = jQuery("#" + id).val();
	console.log("Value to copy: ", value);

	$temp.val(value).select();		
	document.execCommand("copy");
	$temp.remove();

    alert(window.epg.str_copied_msg);
}

function epg_openDownloadMetamaskWindow(e) {
    e.preventDefault();
    var metamaskWindow = window.open("https://metamask.io/", '_blank'
        , 'location=yes,height=' + window.outerHeight + 
            ',width=' + window.outerWidth + 
            ',scrollbars=yes,status=yes');
    metamaskWindow.focus();
}

function epg_task_queue_get() {
    if ('undefined' === typeof window.epg.task_queue) {
        window.epg.task_queue = [];
    }
    return window.epg.task_queue;
}

function epg_task_queue_push(task) {
    console.log("task queue: push");
    var q = epg_task_queue_get();
    q.push(task);
}

function epg_task_queue_empty() {
    var q = epg_task_queue_get();
    return (0 === q.length);
}

function epg_task_queue_top() {
    var q = epg_task_queue_get();
    if (0 === q.length) {
        return null;
    }
    return q[0];
}

function epg_task_queue_pop() {
    console.log("task queue: pop");
    var q = epg_task_queue_get();
    if (0 === q.length) {
        return null;
    }
    return q.shift();
}

function epg_task_queue_process_next_task() {
    console.log("task queue: process next");
    var t = epg_task_queue_top();
    if (!t) {
        return;
    }
    t.call(null, function() {
        // drop just processed task
        epg_task_queue_pop();
        // process next task in a queue
        epg_task_queue_process_next_task();
    });
}

function epg_initWizard(cb) {
    if ('undefined' === typeof cb) {
        cb = function(){};
    }
    jQuery('#rootwizard').removeClass('hidden');
    jQuery('#rootwizard').removeAttr('hidden');
    jQuery('#rootwizard-help-info').removeClass('hidden');
    jQuery('#rootwizard-help-info').removeAttr('hidden');
    if ('undefined' !== typeof window.epg.is_wizard_initialised && window.epg.is_wizard_initialised) {
        cb.call(null, null, null);
        return;
    }
    window.epg.is_wizard_initialised = true;
    jQuery('#rootwizard').bootstrapWizard({
        onTabShow: function(tab, navigation, index) {
            var $total = navigation.find('li').length;
            var $current = index+1;
            var $percent = ($current/$total) * 100;
            jQuery('#rootwizard .progress-bar').css({width:$percent+'%'});
            
            console.log('tab: ' + index);
            switch(index) {
                case EPG_STEP.deposit :
                    var empty = epg_task_queue_empty();
                    epg_task_queue_push(function(cb) {
                        epg_switch_to_step1(function() {
                            if ('undefined' === typeof window.epg['epg-token-advanced-details-step1-opened'] && 
                                !(typeof window !== 'undefined' && typeof window.web3 !== 'undefined')
                            ) {
                                jQuery('#epg-token-advanced-details-step1-button').click();
                                jQuery('#epg-token-advanced-details-step1-button').parent().hide();
                                window.epg['epg-token-advanced-details-step1-opened'] = true;
                            }
                            change_epg_gateway_address();
                            change_epg_data_value();
                            jQuery('#epg-button-next').removeClass('disabled');
                            cb.call(null);
                        });
                    });
                    if (empty) {
                        epg_task_queue_process_next_task();
                    }
                    break;
                case EPG_STEP.payment :
                    var empty = epg_task_queue_empty();
                    epg_task_queue_push(function(cb) {
                        epg_switch_to_step2(function() {
                            if ('undefined' === typeof window.epg['epg-token-advanced-details-step2-opened'] && 
                                !(typeof window !== 'undefined' && typeof window.web3 !== 'undefined')
                            ) {
                                jQuery('#epg-token-advanced-details-step2-button').click();
                                jQuery('#epg-token-advanced-details-step2-button').parent().hide();
                                window.epg['epg-token-advanced-details-step2-opened'] = true;
                            }
                            change_epg_gateway_address_step2();
                            change_epg_data_value_step2();
                            cb.call(null);
                        });
                    });
                    if (empty) {
                        epg_task_queue_process_next_task();
                    }
                    break;
                default :
                    break;
            }
        },
        onTabClick: function(tab, navigation, index) {
            //on tab click disabled
            var is_mm_installed = ('undefined' !== typeof window.epg['web3metamask']);
            return !is_mm_installed;
        },
        onPrevious: function(tab, navigation, index) {
            console.log('prev: ' + index);
            if (!jQuery('#epg-alert').hasClass('hidden') && !jQuery('#epg-alert').is('[hidden]')) {
                // do not change tab if some task is in progress
                return false;
            }
            if ('undefined' === typeof window.epg['web3metamask']) {
                jQuery('#epg-button-next').removeClass('disabled');
            }
        },
        onNext: function(tab, navigation, index) {
            console.log('next: ' + index);
            if (!jQuery('#epg-alert').hasClass('hidden') && !jQuery('#epg-alert').is('[hidden]')) {
                // do not change tab if some task is in progress
                return false;
            }
            var token = jQuery('#epg-token').val();
            switch(index) {
                case EPG_STEP.deposit :
                    break;
                case EPG_STEP.payment :
                    if ('undefined' !== typeof window.epg['web3metamask']) {
                        epg_getValuePaymentEth(function(err, value) {
                            if (err) {
                                console.log(err);
                                epg_alert((token === 'ETH') ? window.epg.str_deposit_eth_failure : window.epg.str_deposit_token_failure);
                                return;
                            }
                            if (null === value || epg_round (value, 5, "PHP_ROUND_HALF_UP") < epg_round (epg_calc_with_token_markup(window.epg.eth_value), 5, "PHP_ROUND_HALF_UP")) {
                                epg_sendTransaction_impl(function(err, result) {
                                    if (err) {
                                        console.log(err);
                                        if (err === window.epg.str_unlock_metamask_account) {
                                            // MM account is locked. here it is equal to no MM at all
                                            jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.payment);
                                            if (token !== 'ETH') {
                                                var timeoutId = setTimeout(function() {
                                                    clearTimeout(timeoutId);
                                                    jQuery('#epg-button-next').removeClass('disabled');
                                                }, 1);
                                            }
                                            return;
                                        }
                                        if (err === window.epg.str_pay_token_failure_insufficient_balance) {
                                            epg_alert(window.epg.str_deposit_token_failure_insufficient_balance);
                                            return;
                                        }
                                        if (err === window.epg.str_pay_token_failure) {
                                            epg_alert(window.epg.str_deposit_token_failure);
                                            return;
                                        }
                                        epg_alert((token === 'ETH') ? window.epg.str_deposit_eth_failure : window.epg.str_deposit_token_failure);
                                        return;
                                    }
                                    epg_alert((token === 'ETH') ? window.epg.str_deposit_eth_success : window.epg.str_deposit_token_success);
                                    jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.payment);
                                    if (token !== 'ETH') {
                                        var timeoutId = setTimeout(function() {
                                            clearTimeout(timeoutId);
                                            jQuery('#epg-button-next').removeClass('disabled');
                                        }, 1);
                                    }
                                });
                            } else {
                                jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.payment);
                                if (token !== 'ETH') {
                                    var timeoutId = setTimeout(function() {
                                        clearTimeout(timeoutId);
                                        jQuery('#epg-button-next').removeClass('disabled');
                                    }, 1);
                                }
                            }
                        });
                        return false;
                    } else {
                        jQuery('#epg-button-next').addClass('disabled');
                    }
                    break;
                case EPG_STEP.result :
                    if ('undefined' !== typeof window.epg['web3metamask']) {
                        epg_getValuePaymentEth(function(err, value) {
                            if (err) {
                                console.log(err);
                                epg_alert((token === 'ETH') ? window.epg.str_pay_eth_failure : window.epg.str_pay_token_failure);
                                return;
                            }
                            if (null === value || epg_round (value, 5, "PHP_ROUND_HALF_UP") < epg_round (epg_calc_with_token_markup(window.epg.eth_value), 5, "PHP_ROUND_HALF_UP")) {
                                epg_sendTransaction_eth_step2_impl();
                            }
//                            jQuery('#rootwizard').bootstrapWizard('show', EPG_STEP.result);
                        });
                        return false;
                    }
                    break;
                default :
                    break;
            }
        },
        nextSelector: '#epg-button-next', 
        previousSelector: '#epg-button-previous'
    });
    cb.call(null, null, null);
}

jQuery(document).ready(function () {
    // hestia theme workaround
    jQuery( '.epg-payment-instructions .navbar a[href*="#"], a.btn[href*="#"]' ).off("click");
    
    if ("undefined" !== typeof window["epg"] && "undefined" !== typeof window.epg["web3Endpoint"]) {
		if (typeof window !== 'undefined' && typeof window.web3 !== 'undefined') {
            var injectedProvider = window.web3.currentProvider;
            window.epg.web3metamask = new Web3(injectedProvider);
			jQuery('#epg-ether-mm-pay').click(epg_pay_ether);			
		} else {
			jQuery('#epg-ether-mm-pay').addClass('hidden');
			jQuery('#epg-ether-mm-pay').attr('hidden', 'hidden');
			jQuery('#epg-ether-download-metamask-button').removeClass('hidden');
			jQuery('#epg-ether-download-metamask-button').removeAttr('hidden');
			jQuery("#epg-ether-download-metamask-button").click(epg_openDownloadMetamaskWindow);
            
			jQuery('#epg-download-metamask-button').removeClass('hidden');
			jQuery('#epg-download-metamask-button').removeAttr('hidden');
			jQuery("#epg-download-metamask-button").click(epg_openDownloadMetamaskWindow);

			jQuery('#epg-button-next').removeClass('offset-md-8');
		}

        setInterval(function() {
            if (typeof window !== 'undefined' && typeof window.web3 !== 'undefined') {
                epg_getUserAccounts(function(err, accounts) {

                    if (err) {
                        console.log(err); 
                        return;
                    }

                    if (0 === accounts.length) {
                        if (jQuery('#epg-payment-success-message-wrapper').hasClass('hidden') || jQuery('#epg-payment-success-message-wrapper').is('[hidden]')) {
                            jQuery('#epg-unlock-metamask-message-wrapper').removeClass('hidden');
                            jQuery('#epg-unlock-metamask-message-wrapper').removeAttr('hidden');
                        }
                        jQuery('#epg-button-previous').removeClass('hidden');
                        jQuery('#epg-button-previous').removeAttr('hidden');
                        
                        jQuery('#epg-wizard-buttons-group').removeClass('offset-md-2');
                        return;
                    }
                    jQuery('#epg-unlock-metamask-message-wrapper').addClass('hidden');
                    jQuery('#epg-unlock-metamask-message-wrapper').attr('hidden', 'hidden');
                });
            } else {
                if (jQuery('#epg-payment-success-message-wrapper').hasClass('hidden') || jQuery('#epg-payment-success-message-wrapper').is('[hidden]')) {
                    jQuery('#epg-unlock-metamask-message-wrapper').removeClass('hidden');
                    jQuery('#epg-unlock-metamask-message-wrapper').removeAttr('hidden');
                }
                jQuery('#epg-button-previous').removeClass('hidden');
                jQuery('#epg-button-previous').removeAttr('hidden');
                        
                jQuery('#epg-wizard-buttons-group').removeClass('offset-md-2');
            }
        }, 3000);

        if ("undefined" !== typeof window.epg.web3Endpoint) {
            window.epg.web3 = new Web3(new Web3.providers.HttpProvider(window.epg.web3Endpoint));
        }
        if (window.epg.web3metamask) {
            // https://ethereum.stackexchange.com/a/23905/34760
            window.epg.web3metamask.version.getNetwork(function(err, netId) {
                if (err) {
                    console.log(err); 
                    return;
                }
                switch (netId) {
                    case "1":
                        window.epg.mm_network_mismatch = (-1 === window.epg.web3Endpoint.indexOf('mainnet'));
                        break
//                    case "2":
//                        console.log('This is the deprecated Morden test network.')
//                        break
                    case "3":
                        window.epg.mm_network_mismatch = (-1 === window.epg.web3Endpoint.indexOf('ropsten'));
                        break
                    case "4":
                        window.epg.mm_network_mismatch = (-1 === window.epg.web3Endpoint.indexOf('rinkeby'));
                        break;
                    case "42":
                        window.epg.mm_network_mismatch = (-1 === window.epg.web3Endpoint.indexOf('kovan'));
                        break;
                    default: {
                        console.log('This is an unknown network.');
                        window.epg.mm_network_mismatch = true;
                    }
                }
                if (window.epg.mm_network_mismatch) {
                    jQuery('#epg-metamask-network-mismatch-message-wrapper').removeClass('hidden');
                    jQuery('#epg-metamask-network-mismatch-message-wrapper').removeAttr('hidden');
                } else {
                    jQuery('#epg-metamask-network-mismatch-message-wrapper').addClass('hidden');
                    jQuery('#epg-metamask-network-mismatch-message-wrapper').attr('hidden', 'hidden');
                }
            });
        }
        epg_show_wait_icon();
        epg_getValuePaymentEth(function(err, value, currencyAddress) {
            if (err) {
                console.log(err);
                epg_alert(err);
                epg_hide_wait_icon();
                return;
            }
            if (null === value) {
                epg_hide_wait_icon();
                jQuery('#epg-payment-incomplete-message-wrapper').removeClass('hidden');
                jQuery('#epg-payment-incomplete-message-wrapper').removeAttr('hidden');
                return;
            }
            // ETH is encoded as address 0x0000000000000000000000000000000000000001
            if (currencyAddress !== "0x0000000000000000000000000000000000000001") {
                if (epg_round (value, 5, "PHP_ROUND_HALF_UP") < epg_round (epg_calc_with_token_markup(window.epg.eth_value), 5, "PHP_ROUND_HALF_UP")) {
                    epg_hide_wait_icon();
                    jQuery('#epg-payment-incomplete-message-wrapper').removeClass('hidden');
                    jQuery('#epg-payment-incomplete-message-wrapper').removeAttr('hidden');
                    return;
                }
            } else {
                if (epg_round (value, 5, "PHP_ROUND_HALF_UP") < epg_round (window.epg.eth_value, 5, "PHP_ROUND_HALF_UP")) {
                    epg_hide_wait_icon();
                    jQuery('#epg-payment-incomplete-message-wrapper').removeClass('hidden');
                    jQuery('#epg-payment-incomplete-message-wrapper').removeAttr('hidden');
                    return;
                }
            }
            jQuery('#epg-payment-success-message-wrapper').removeClass('hidden');
            jQuery('#epg-payment-success-message-wrapper').removeAttr('hidden');
            jQuery('#epg-ether-payment').addClass('hidden');
            jQuery('#epg-ether-payment').attr('hidden', 'hidden');
            jQuery('#rootwizard-help-info').addClass('hidden');
            jQuery('#rootwizard-help-info').attr('hidden', 'hidden');
            jQuery('#epg-unlock-metamask-message-wrapper').addClass('hidden');
            jQuery('#epg-unlock-metamask-message-wrapper').attr('hidden', 'hidden');
            jQuery('#epg-token-wrapper').addClass('hidden');
            jQuery('#epg-token-wrapper').attr('hidden', 'hidden');
            epg_hide_wait_icon();
        });
		jQuery('#epg-token').change(epg_tokenChange);
		jQuery(".epg-copy-button").click(epg_copyAddress);
        epg_getCurrencyPayment(function(err, currencyAddress) {
            if (err) {
                console.log(err);
                alert(err);
        		epg_fill_payment_info();
                return;
            }
            if (currencyAddress === "0x0000000000000000000000000000000000000000" ||
                currencyAddress === "0x") {
                // no payment was performed
        		epg_fill_payment_info();
                return;
            }
//            // ETH is encoded as address 0x0000000000000000000000000000000000000001
//            if (currencyAddress === "0x0000000000000000000000000000000000000001") {
//        		epg_fill_payment_info();
//                return;
//            }
//            var tokenInfo = epg_getTokenInfoByAddress(currencyAddress);
//            if (tokenInfo) {
//                jQuery('#epg-token').val(tokenInfo.symbol);
//            }
//            epg_fill_payment_info();
        });
	}
    
    // https://stackoverflow.com/a/19538231/4256005
    window.addEventListener("beforeunload", function (e) {
        if (!jQuery('#epg-ether-alert').hasClass('hidden') && !jQuery('#epg-ether-alert').is('[hidden]')) {
            // some task is in progress
            
            var confirmationMessage = window.epg.str_page_unload_text;

            (e || window.event).returnValue = confirmationMessage; //Gecko + IE
            return confirmationMessage;                            //Webkit, Safari, Chrome
        }
    });

    // Init QR codes
    jQuery('.epg-ether-canvas-qr1').qrcode({
        text : jQuery('#epg-ether-value').val()
    });
    jQuery('.epg-ether-canvas-qr2').qrcode({
        text : jQuery('#epg-ether-gateway-address').val()
    });
    jQuery('.epg-ether-canvas-qr3').qrcode({
        text : jQuery('#epg-ether-data-value').val()
    });
    
    jQuery('.epg-token-step1-canvas-qr1').qrcode({
        text : jQuery('#epg-value').val()
    });
    
    change_epg_gateway_address();
    jQuery('#epg-gateway-address').change(change_epg_gateway_address);

    change_epg_data_value();
    jQuery('#epg-data-value').change(change_epg_data_value);
    
    jQuery('.epg-token-step2-canvas-qr1').qrcode({
        text : jQuery('#epg-value-step2').val()
    });
    
    change_epg_gateway_address_step2();
    jQuery('#epg-gateway-address-step2').change(change_epg_gateway_address_step2);

    change_epg_data_value_step2();
    jQuery('#epg-data-value-step2').change(change_epg_data_value_step2);
});

function change_epg_gateway_address() {
    jQuery('.epg-token-step1-canvas-qr2 > canvas').remove();
    jQuery('.epg-token-step1-canvas-qr2').qrcode({
        text : jQuery('#epg-gateway-address').val()
    });
}
function change_epg_data_value() {
    jQuery('.epg-token-step1-canvas-qr3 > canvas').remove();
    jQuery('.epg-token-step1-canvas-qr3').qrcode({
        text : jQuery('#epg-data-value').text()
    });
}
function change_epg_gateway_address_step2() {
    jQuery('.epg-token-step2-canvas-qr2 > canvas').remove();
    jQuery('.epg-token-step2-canvas-qr2').qrcode({
        text : jQuery('#epg-gateway-address-step2').val()
    });
}
function change_epg_data_value_step2() {
    jQuery('.epg-token-step2-canvas-qr3 > canvas').remove();
    jQuery('.epg-token-step2-canvas-qr3').qrcode({
        text : jQuery('#epg-data-value-step2').text()
    });
}
