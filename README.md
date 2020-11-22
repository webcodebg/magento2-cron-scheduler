# Release Notes
- v 1.0.9 - Fix issue with Invalid theme https://github.com/kiwicommerce/magento2-cron-scheduler/issues/25

# Fork
- Oct. 2020 - [KiwiCommerce](https://kiwicommerce.co.uk/) stop maintaining this extension. We will try to continue his development.

# Magento 2 - Cron Scheduler by [KiwiCommerce](https://kiwicommerce.co.uk/)
- Easily set up cron jobs
- Manage cron jobs from the backend
- Have an organised and easily managed timeline feature
- See the longest running cron job
- Receive an email notification if any cron job produces a fatal error
- View the actual load on CPU/memory by cron job execution
- Automatically stop long running jobs
- Our Magento 2 extension Cron Scheduler is free to download.

## **Installation** 
1. Composer Installation
      - Navigate to your Magento root folder<br />
            `cd path_to_the_magento_root_directory`
      - Then run the following command<br />
            `composer require kiwicommerce/module-cron-scheduler`<br />
            For Magento version < v2.3.5, please use the following older version<br />
            `composer require kiwicommerce/module-cron-scheduler:1.0.6`
      - Make sure that composer finished the installation without errors

 2. Command Line Installation
      - Backup your web directory and database.
      - Download the latest Cron Scheduler installation package kiwicommerce-cron-scheduler-vvvv.zip from [here](https://github.com/kiwicommerce/magento2-cron-scheduler/releases)
      - Navigate to your Magento root folder<br />
            `cd path_to_the_magento_root_directory`<br />
      - Upload contents of the Cron Scheduler installation package to your Magento root directory
      - Then run the following command<br />
            `php bin/magento module:enable KiwiCommerce_CronScheduler`<br />
   
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
You need to follow this path. Stores > Configuration > KIWICOMMERCE EXTENSIONS > Cron Scheduler

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/Configuration_cronscheduler.png"/> <br/>

### Dashboard
You can analyze the actual time taken by each job on magento dashboard.

<img src="https://kiwicommerce.co.uk/wp-content/uploads/2018/05/cronscheduler_dashboard.png" height="250"/> <br/>

## Contribution
Well unfortunately there is no formal way to contribute, we would encourage you to feel free and contribute by:
 
  - Creating bug reports, issues or feature requests on <a target="_blank" href="https://github.com/webcodebg/magento2-cron-scheduler/issues">Github</a>
  - Submitting pull requests for improvements.


