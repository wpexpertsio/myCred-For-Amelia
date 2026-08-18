/**
 * myCred Amelia Frontend Integration
 *
 * Receives data through wp_localize_script as `myCredData`.
 * Adds point conversion details to Amelia's payment summary and blocks
 * checkout when the logged-in customer does not have enough points.
 */
(function ($) {
  'use strict';

  var data = (typeof myCredData !== 'undefined') ? myCredData : {};
  var enabled = parseInt(data.enabled, 10) !== 0;
  var prefix = data.prefix || '';
  var suffix = data.suffix || '';
  var balance = parseFloat(data.balance) || 0;
  var userId = parseInt(data.userId, 10) || 0;
  var insufficientMsg = data.insufficientMsg || '';
  var pointType = data.pointType || 'points';
  var conversionRate = parseFloat(data.conversionRate) || 1;
  var rounding = data.rounding || 'ceil';
  var buyPointsUrl = data.buyPointsUrl || '/buypoints';

  if (!enabled || userId === 0) return;

  insufficientMsg = insufficientMsg.replace(/%buy_points_url%/g, buyPointsUrl);

  var styleId = 'mycred-amelia-balance-css';
  if (!document.getElementById(styleId)) {
    var css =
      '.amelia-v2-booking #amelia-container .mycred-points-row {\n' +
      '  display: flex !important;\n' +
      '  justify-content: space-between !important;\n' +
      '  align-items: center !important;\n' +
      '  gap: 16px !important;\n' +
      '  font-size: 15px !important;\n' +
      '  font-weight: 500 !important;\n' +
      '  line-height: 1.33333 !important;\n' +
      '  color: var(--am-c-pay-text, #1A2C37) !important;\n' +
      '  padding: 8px 0 0 !important;\n' +
      '  margin: 0 !important;\n' +
      '  border: none !important;\n' +
      '  background: transparent !important;\n' +
      '  box-sizing: border-box !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .mycred-required-points-row {\n' +
      '  margin-top: 8px !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .mycred-points-row span {\n' +
      '  font-size: 15px !important;\n' +
      '  font-weight: 500 !important;\n' +
      '  line-height: 1.33333 !important;\n' +
      '  font-family: var(--am-font-family, inherit) !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .mycred-points-row span.mycred-label {\n' +
      '  color: var(--am-c-pay-text, #1A2C37) !important;\n' +
      '  white-space: normal !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .mycred-points-row span.mycred-value {\n' +
      '  color: var(--am-c-pay-primary, #1246D6) !important;\n' +
      '  white-space: nowrap !important;\n' +
      '  text-align: right !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .mycred-points-row span.mycred-value.insufficient {\n' +
      '  color: var(--am-c-pay-error, var(--am-c-error, #e53935)) !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-app-info-total.am-single-row {\n' +
      '  padding-top: 10px !important;\n' +
      '  margin-top: 0px !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient p,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient a,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h1,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h2,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h3,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h4,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h5,\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient h6 {\n' +
      '  color: #e53935 !important;\n' +
      '  font-size: 14px !important;\n' +
      '  font-weight: 500 !important;\n' +
      '  line-height: 1.42857 !important;\n' +
      '  text-decoration: none !important;\n' +
      '  display: inline !important;\n' +
      '  margin: 0 !important;\n' +
      '  padding: 0 !important;\n' +
      '  text-align: center !important;\n' +
      '  font-family: var(--am-f-text, inherit) !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient a {\n' +
      '  color: var(--am-c-pay-primary, revert) !important;\n' +
      '  text-decoration: underline !important;\n' +
      '}\n' +
      '.amelia-v2-booking #amelia-container .am-fs__payments-sentence.mycred-insufficient a * {\n' +
      '  color: inherit !important;\n' +
      '}';
    var styleTag = document.createElement('style');
    styleTag.id = styleId;
    styleTag.textContent = css;
    document.head.appendChild(styleTag);
  }

  function normalizePointNumber(value) {
    var rounded = Math.round((parseFloat(value) || 0) * 1000000) / 1000000;
    return Number.isInteger(rounded) ? String(rounded) : String(rounded);
  }

  function formatPointType(value) {
    var unit = pointType;
    var amount = Math.abs(parseFloat(value) || 0);

    if (amount === 1 && unit.slice(-1).toLowerCase() === 's') {
      unit = unit.slice(0, -1);
    }

    return unit;
  }

  function formatPoints(value) {
    var text = normalizePointNumber(value);
    if (prefix) text = prefix + ' ' + text;
    if (suffix) text += ' ' + suffix;
    if (!prefix && !suffix && pointType) text += ' ' + formatPointType(value);
    return text;
  }

  function calculateRequiredPoints(totalPrice) {
    var raw = Math.max(0, parseFloat(totalPrice) || 0) * conversionRate;

    if (rounding === 'floor') {
      return Math.floor(raw);
    }

    if (rounding === 'round') {
      return Math.round(raw);
    }

    return Math.ceil(raw);
  }

  function replaceCurrency(node) {
    if (!node) return;

    if (node.nodeType === Node.TEXT_NODE) {
      if (node.nodeValue && (/\u00c2\u00a5|\u00a5/).test(node.nodeValue)) {
        node.nodeValue = node.nodeValue.replace(/\u00c2\u00a5|\u00a5/g, prefix);
      }
      return;
    }

    if (node.nodeType === Node.ELEMENT_NODE) {
      var tag = node.tagName.toLowerCase();
      if (tag === 'script' || tag === 'style') return;

      var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null, false);
      var textNode;
      while ((textNode = walker.nextNode())) {
        if (textNode.nodeValue && (/\u00c2\u00a5|\u00a5/).test(textNode.nodeValue)) {
          textNode.nodeValue = textNode.nodeValue.replace(/\u00c2\u00a5|\u00a5/g, prefix);
        }
      }
    }
  }

  function parseTotalPrice(totalRow) {
    var amountSpans = totalRow.querySelectorAll('.am-amount, span:last-child');
    var totalText = '';

    amountSpans.forEach(function (span) {
      totalText += span.textContent || '';
    });

    return parseFloat(totalText.replace(/,/g, '').replace(/[^0-9.]/g, '')) || 0;
  }

  function upsertPointsRow(totalRow, className, labelText, valueText, insufficient) {
    var parent = totalRow.parentNode;
    if (!parent) return null;

    var row = parent.querySelector('.' + className);
    var label;
    var value;

    if (!row) {
      row = document.createElement('div');
      row.className = 'am-fs__payments-app-info-remaining mycred-points-row ' + className;

      label = document.createElement('span');
      label.className = 'mycred-label';

      value = document.createElement('span');
      value.className = 'am-amount mycred-value';

      row.appendChild(label);
      row.appendChild(value);
      parent.insertBefore(row, totalRow);
    } else {
      label = row.querySelector('.mycred-label');
      value = row.querySelector('.mycred-value');
    }

    if (label && label.textContent !== labelText) {
      label.textContent = labelText;
    }

    if (value && value.textContent !== valueText) {
      value.textContent = valueText;
    }

    if (value) {
      value.classList.toggle('insufficient', !!insufficient);
    }

    return row;
  }

  function removePointsRow(totalRow, className) {
    var parent = totalRow.parentNode;
    var row = parent ? parent.querySelector('.' + className) : null;

    if (row) {
      row.remove();
    }
  }

  function findContinueButton() {
    var footer = document.querySelector('.am-fs__main-footer');
    if (!footer) return null;

    return footer.querySelector('button:not(.am-button--secondary):not(.el-button--secondary)') ||
      footer.querySelector('button:last-child');
  }

  function injectPointRows(totalRows) {
    if (!totalRows) {
      totalRows = document.querySelectorAll('.am-fs__payments-app-info-total');
    }

    totalRows.forEach(function (totalRow) {
      var totalPrice = parseTotalPrice(totalRow);
      var requiredPoints = calculateRequiredPoints(totalPrice);
      var afterBalance = balance - requiredPoints;
      var insufficient = requiredPoints > balance;

      upsertPointsRow(totalRow, 'mycred-required-points-row', 'Required Points:', formatPoints(requiredPoints), false);
      upsertPointsRow(totalRow, 'mycred-balance-row', 'Your Points Balance:', formatPoints(balance), insufficient);

      if (insufficient) {
        removePointsRow(totalRow, 'mycred-after-balance-row');
      } else {
        upsertPointsRow(totalRow, 'mycred-after-balance-row', 'Balance After Booking:', formatPoints(afterBalance), false);
      }

      var continueBtn = findContinueButton();
      var paymentsContainer = totalRow.closest('.am-fs__payments') || document.querySelector('.am-fs__payments');
      var sentence = paymentsContainer ? paymentsContainer.querySelector('.am-fs__payments-sentence') : null;

      if (insufficient) {
        if (sentence) {
          var warningTarget = sentence.querySelector('p') || sentence;
          if (warningTarget.innerHTML !== insufficientMsg) {
            warningTarget.innerHTML = insufficientMsg;
          }
          sentence.classList.add('mycred-insufficient');
        }

        if (continueBtn && continueBtn.style.display !== 'none') {
          continueBtn.style.setProperty('display', 'none', 'important');
        }
      } else {
        if (sentence) {
          var successTarget = sentence.querySelector('p') || sentence;
          var sufficientText = 'The payment will be done with ' + formatPoints(requiredPoints) + '.';
          if (successTarget.textContent !== sufficientText) {
            successTarget.textContent = sufficientText;
          }
          sentence.classList.remove('mycred-insufficient');
        }

        if (continueBtn && continueBtn.style.display === 'none') {
          continueBtn.style.removeProperty('display');
        }
      }
    });
  }

  var observer = new MutationObserver(function (mutations) {
    observer.disconnect();

    try {
      var hasAdditions = false;

      for (var i = 0; i < mutations.length; i++) {
        var addedNodes = mutations[i].addedNodes;

        for (var j = 0; j < addedNodes.length; j++) {
          replaceCurrency(addedNodes[j]);
          hasAdditions = true;
        }
      }

      var totalRows = document.querySelectorAll('.am-fs__payments-app-info-total');
      if (hasAdditions || totalRows.length > 0) {
        injectPointRows(totalRows);
      }
    } finally {
      observer.observe(document.body, { childList: true, subtree: true });
    }
  });

  $(function () {
    replaceCurrency(document.body);
    injectPointRows();
    observer.observe(document.body, { childList: true, subtree: true });
  });
})(jQuery);

