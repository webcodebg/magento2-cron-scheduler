
# Magento 2 - Cron Scheduler by [Webcode](https://webcode.bg/)
- Easily set up cron jobs
- Manage cron jobs from the backend
- Have an organised and easily managed timeline feature
- See the longest running cron job
- Receive an email notification if any cron job produces a fatal error
- View the actual load on CPU/memory by cron job execution
- Automatically stop long running jobs, with a limit per job

## Requirements
- PHP 8.3 or newer
- Magento 2.4.4 or newer (`magento/framework` >= 103.0.4)

For older Magento / PHP versions use the 1.x line: `composer require webcodebg/magento2-cron-scheduler:^1.0`.

## Release Notes
- v 2.1.x
  - The kill threshold for long running jobs is set per job instead of a fixed 3 hours (see [Long running jobs](#long-running-jobs)).
  - A non-numeric per-job limit removes the override instead of being saved as `0` ("never kill").
  - Rows without a pid are never killed.
  - `cron:run --exclude-group` is honoured.
  - The Error's message is kept in `cron_schedule.messages`.
  - `isXmlHttpRequest()` guard in the Validation controllers.
- v 2.0.0 - **Breaking:** the module is renamed from `KiwiCommerce_CronScheduler` to `Webcode_CronScheduler` (PHP namespace `Webcode\CronScheduler`). Declarative schema (`etc/db_schema.xml`) instead of setup scripts, helpers moved to `Service\*`, timeline overhaul, "Regenerate schedules" and "Clear past logs" actions, escaped template output, PHP 8.3+ / Magento 2.4.4+.
- v 1.0.9 - Fix issue with Invalid theme https://github.com/kiwicommerce/magento2-cron-scheduler/issues/25

### Upgrading from 1.x (KiwiCommerce_CronScheduler)
The module name, the PHP namespace and the ACL resource ids changed with 2.0.0.
- Custom code that references `KiwiCommerce\CronScheduler\...` classes must be updated to `Webcode\CronScheduler\...`.
- Admin roles must be granted the new `Webcode_CronScheduler::*` resources again (System > Permissions > User Roles).
- Configuration under `cronscheduler/*` and the extra `cron_schedule` columns are kept.

## Fork
- Oct. 2020 - [KiwiCommerce](https://kiwicommerce.co.uk/) stopped maintaining this extension. [Webcode](https://webcode.bg/) continues its development.

## **Installation**
1. Composer Installation
      - Navigate to your Magento root folder<br />
            `cd path_to_the_magento_root_directory`
      - Then run the following command<br />
            `composer require webcodebg/magento2-cron-scheduler`
      - Make sure that composer finished the installation without errors

 2. Manual Installation
      - Backup your web directory and database.
      - Copy the contents of this repository to `app/code/Webcode/CronScheduler`
      - Then run the following command<br />
            `php bin/magento module:enable Webcode_CronScheduler`<br />

- After install the extension, run the following command
```
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```
- Log out from the backend and login again.

## Features
### Cron Jobs
You will have a list of all cron jobs with their groups, cron expression, job code and other useful information.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/cronjob.png"/><br/>

### Cron Job Schedule list
You will have a list of scheduled jobs with their respective status.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/schedule-list.png"/><br/>

### Add New Cron Job
You can create a new cron job by clicking on Add New Cron Job. You need to add different valid information while creating it.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/addnewcronjob.png"/> <br/>

### Timeline
You will have a beautiful timeline for each cron job.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/timeline.png" /> <br/>

### Configuration
You need to follow this path. Stores > Configuration > WEBCODE > Cron Scheduler

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/Configuration_cronscheduler.png"/> <br/>

### Long running jobs
Every 15 minutes the module kills (SIGKILL) each job in status `running` that started longer ago than its limit.
The limit is resolved per job, in minutes:
1. `cronscheduler/max_runtime/<job_code>`
2. `cronscheduler/general/max_runtime` (Stores > Configuration, default `180`)
3. `180`

`0` means the job is never killed. A per-job limit can be set from the job form (Max Runtime), with
`bin/magento config:set cronscheduler/max_runtime/<job_code> <minutes>`, or in the `config.xml` of the module that owns the job:
```xml
<default><cronscheduler><max_runtime><job_code>240</job_code></max_runtime></cronscheduler></default>
```

### Dashboard
You can analyze the actual time taken by each job on magento dashboard.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/cronscheduler_dashboard.png" height="250"/> <br/>

## License
[Open Software License 3.0 (OSL-3.0)](LICENSE.txt)

## Contribution
Well unfortunately there is no formal way to contribute, we would encourage you to feel free and contribute by:

  - Creating bug reports, issues or feature requests on <a target="_blank" href="https://github.com/webcodebg/magento2-cron-scheduler/issues">Github</a>
  - Submitting pull requests for improvements.
