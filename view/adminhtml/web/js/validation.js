/*
 * @package      Webcode_CronScheduler
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright Copyright © 2020–2026 Webcode (https://webcode.bg/)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0 (OSL-3.0)
 */

require(
    [
        'Magento_Ui/js/lib/validation/validator',
        'jquery',
        'mage/url',
        'mage/translate'
    ],
    function (validator, $, urlBuilder) {

        /*
         * These rules run through Magento_Ui's synchronous validator (UI-component form):
         * addRule() callbacks MUST return a boolean immediately — the validator does not
         * await promises, so an asynchronous request would resolve after the verdict is
         * read and the rule would always pass. The synchronous request below is therefore
         * required by the validator contract, NOT an oversight; do not switch it to
         * async unless the form is migrated off Magento_Ui/js/lib/validation/validator.
         * Server-side validation in the Save controller is the authoritative check.
         *
         * A tiny per-value cache avoids re-issuing an identical blocking request when the
         * same value is validated multiple times in one cycle (e.g. blur + submit).
         */
        var remoteCache = {};

        function remoteCheck(url, data, positiveWhenFound) {
            var cacheKey = url + '|' + JSON.stringify(data);
            if (remoteCache.hasOwnProperty(cacheKey)) {
                return remoteCache[cacheKey];
            }

            var found = false;
            $.ajax({
                type: "POST",
                async: false, // required: see note above
                url: url,
                data: data,
                success: function (response) {
                    found = response.success === true;
                }
            });

            var result = positiveWhenFound ? found : !found;
            remoteCache[cacheKey] = result;

            return result;
        }

        validator.addRule(
            'uniquejobcode',
            function (value) {
                // Valid when the job code is NOT already taken on the server.
                return remoteCheck(
                    urlBuilder.build("cronscheduler/validation/uniquejobcode"),
                    {jobcode: value},
                    false
                );
            }
            ,
            $.mage.__('Please enter a valid job code.')
        );

        validator.addRule(
            'classexistance',
            function (value) {
                return remoteCheck(
                    urlBuilder.build("cronscheduler/validation/classexistance"),
                    {classpath: value},
                    true
                );
            }
            ,
            $.mage.__('Please enter valid class instance.')
        );

        validator.addRule(
            'methodexistance',
            function (value) {
                return remoteCheck(
                    urlBuilder.build("cronscheduler/validation/methodexistance"),
                    {classpath: $("input[name=instance]").val(), methodname: value},
                    true
                );
            }
            ,
            $.mage.__('Please enter valid class method.')
        );

        validator.addRule(
            'checkexpression',
            function (value) {
                return remoteCheck(
                    urlBuilder.build("cronscheduler/validation/cronexpression"),
                    {expression: value},
                    true
                );
            }
            ,
            $.mage.__('Please enter a valid cron expression.')
        );

        $.validator.addMethod(
            'validate-comma-separated-emails',
            function (emaillist) {
                emaillist = emaillist.trim();
                if (emaillist.charAt(0) === ',' || emaillist.charAt(emaillist.length - 1) === ',') {
                    return false;
                }
                var emails = emaillist.split(',');
                var invalidEmails = [];
                for (i = 0; i < emails.length; i++) {
                    var v = emails[i].trim();
                    if (!Validation.get('validate-email').test(v)) {
                        invalidEmails.push(v);
                    }
                }

                return !invalidEmails.length;
            },
            $.mage.__('Please enter a valid email address.')
        );
    }
);
