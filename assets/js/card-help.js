/*
 * Dashboard card help tooltips.
 *
 * Decorates Shopaholic indicator cards with an (i) icon. Hovering it shows a
 * tooltip with the live card value, the derived previous-period value and a
 * practical explanation. Cards are matched by translated title; the payload
 * rides in on the data-help attribute of this script tag.
 *
 * The dashboard is a Vue app that re-renders widgets on interval changes, so
 * a MutationObserver re-decorates cards whose icon was wiped.
 */
(function () {
    'use strict';

    var obScript = document.getElementById('dashboardshopaholic-card-help');
    if (!obScript || !obScript.dataset.help) {
        return;
    }

    var obConfig;
    try {
        obConfig = JSON.parse(obScript.dataset.help);
    } catch (obError) {
        return;
    }

    var obTip = null;
    var iOpenTimer = null;

    function removeTip() {
        if (iOpenTimer !== null) {
            window.clearTimeout(iOpenTimer);
            iOpenTimer = null;
        }
        if (obTip !== null) {
            obTip.remove();
            obTip = null;
        }
    }

    /*
     * "NOK 77.86" -> {prefix: 'NOK ', number: 77.86, decimals: 2, suffix: ''}
     * Handles both dot and comma decimals; the shorter trailing group wins.
     */
    function parseValue(sText) {
        var arMatch = sText.match(/^([^0-9-]*)(-?[0-9][0-9\s .,]*)(.*)$/);
        if (arMatch === null) {
            return null;
        }

        var sNumber = arMatch[2].replace(/[\s ]/g, '');
        var sDecimalSeparator = null;
        var arDecimalMatch = sNumber.match(/[.,](\d{1,2})$/);
        if (arDecimalMatch !== null) {
            sDecimalSeparator = sNumber.charAt(sNumber.length - arDecimalMatch[1].length - 1);
        }

        var sNormalized = '';
        for (var iIndex = 0; iIndex < sNumber.length; iIndex++) {
            var sChar = sNumber.charAt(iIndex);
            if (sChar === '.' || sChar === ',') {
                if (sDecimalSeparator !== null && sChar === sDecimalSeparator && iIndex === sNumber.lastIndexOf(sChar)) {
                    sNormalized += '.';
                }
            } else {
                sNormalized += sChar;
            }
        }

        var fValue = parseFloat(sNormalized);
        if (isNaN(fValue)) {
            return null;
        }

        return {
            prefix: arMatch[1],
            number: fValue,
            decimals: arDecimalMatch === null ? 0 : arDecimalMatch[1].length,
            separator: sDecimalSeparator === null ? '.' : sDecimalSeparator,
            suffix: arMatch[3]
        };
    }

    function formatLike(obParsed, fNumber) {
        var sFormatted;
        try {
            sFormatted = fNumber.toLocaleString(document.documentElement.lang || undefined, {
                minimumFractionDigits: obParsed.decimals,
                maximumFractionDigits: obParsed.decimals
            });
        } catch (obError) {
            sFormatted = fNumber.toFixed(obParsed.decimals);
        }
        return obParsed.prefix + sFormatted + obParsed.suffix;
    }

    function buildLiveLine(obCard) {
        var obValueSpan = obCard.querySelector('.total-container > span:first-child');
        if (obValueSpan === null) {
            return null;
        }

        var sValue = obValueSpan.textContent.trim();
        var sLine = obConfig.now + ': ' + sValue;

        var obDiff = obCard.querySelector('.prev-period-marker');
        if (obDiff === null) {
            return sLine;
        }

        // The marker shows the ABSOLUTE difference formatted like the metric
        // ("940", "NOK 13.00", "0.1%"), so previous = current -/+ difference
        var obDiffSpan = obDiff.querySelector('span');
        var sDiffAbs = obDiffSpan === null ? '' : obDiffSpan.textContent.trim();
        var bNegative = obDiff.classList.contains('negative');
        var bNeutral = obDiff.classList.contains('neutral');
        var sSigned = (bNeutral ? '' : (bNegative ? '-' : '+')) + sDiffAbs;

        var sBefore = sSigned;
        var obValueParsed = parseValue(sValue);
        var obDiffParsed = parseValue(sDiffAbs);
        if (obValueParsed !== null && obDiffParsed !== null) {
            var fPrevious = bNeutral
                ? obValueParsed.number
                : obValueParsed.number + (bNegative ? obDiffParsed.number : -obDiffParsed.number);
            sBefore = formatLike(obValueParsed, fPrevious) + ' (' + sSigned + ')';
        }

        return sLine + ' · ' + obConfig.before + ': ' + sBefore;
    }

    function showTip(obIcon, obCard, sHelpText) {
        removeTip();

        obTip = document.createElement('div');
        obTip.className = 'dsb-card-help-tip';

        var sLiveLine = buildLiveLine(obCard);
        if (sLiveLine !== null) {
            var obLive = document.createElement('strong');
            obLive.textContent = sLiveLine;
            obTip.appendChild(obLive);
        }

        var obText = document.createElement('span');
        obText.textContent = sHelpText;
        obTip.appendChild(obText);

        document.body.appendChild(obTip);

        var obRect = obIcon.getBoundingClientRect();
        var iLeft = Math.min(obRect.left, window.innerWidth - obTip.offsetWidth - 12);
        obTip.style.left = Math.max(8, iLeft) + 'px';
        obTip.style.top = (obRect.bottom + 8) + 'px';

        window.requestAnimationFrame(function () {
            if (obTip !== null) {
                obTip.classList.add('is-open');
            }
        });
    }

    function decorateCard(obCard) {
        if (obCard.querySelector('.dsb-card-help-icon') !== null) {
            return;
        }

        var obTitle = obCard.querySelector('.widget-title span');
        if (obTitle === null) {
            return;
        }

        var sHelpText = obConfig.cards[obTitle.textContent.trim()];
        if (sHelpText === undefined) {
            return;
        }

        var obIcon = document.createElement('span');
        obIcon.className = 'dsb-card-help-icon';
        obIcon.textContent = 'i';
        obIcon.setAttribute('aria-label', sHelpText);

        obIcon.addEventListener('mouseenter', function () {
            removeTip();
            iOpenTimer = window.setTimeout(function () {
                iOpenTimer = null;
                showTip(obIcon, obCard, sHelpText);
            }, 150);
        });
        obIcon.addEventListener('mouseleave', removeTip);

        obTitle.parentNode.appendChild(obIcon);
    }

    function decorateAll() {
        document.querySelectorAll('.dashboard-report-widget-indicator').forEach(decorateCard);
    }

    var bScheduled = false;
    var obObserver = new MutationObserver(function () {
        if (bScheduled) {
            return;
        }
        bScheduled = true;
        window.requestAnimationFrame(function () {
            bScheduled = false;
            decorateAll();
        });
    });

    obObserver.observe(document.body, { childList: true, subtree: true });
    document.addEventListener('scroll', removeTip, true);
    decorateAll();
})();
