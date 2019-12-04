# DataCue for PrestaShop Integration

Learn how to connect DataCue for PrestaShop.

## Before You Start

- For the most up-to-date install instructions, read our [DataCue guide for PrestaShop](https://help.datacue.co/prestashop/installation.html). 

## Installation and Setup

**Here’s a brief overview of this multi-step process.**

- Clone this repository to your local machine and run `composer install` from the folder
- Zip the whole folder using your favourite ZIP compression tool
- Install the plugin on your PrestaShop Admin site by clicking on **Modules** > **Module Manager** > **Upload a module** > **Select File** > select the ZIP file you just created.
- Once installed, click **Configure**
- Connect the plugin with your DataCue API Key and Secret (you can find it on your dashboard) and press save.
- Depending on the size of your store the sync process can take a few mins to a few hours.

## Disable or Uninstall the Plugin

When you uninstall DataCue for PrestaShop, we remove all changes made to your store including the Javascript. We also immediately stop syncing any changes to your store data with DataCue.
To uninstall DataCue for PrestaShop, follow these steps.

1. Log in to your PrestaShop admin panel.

2. In the left navigation panel, click **Modules** > **Module Manager**, and find the section named **DataCue for PrestaShop**.

3. Click the **Disable** button on the drop down menu.

4. Click the **Uninstall** button on the drop down menu.

## Before uploading the module to PrestaShop Addons Marketplace

1. Copy the module folder to a temporary place.

2. Enter the temporary folder.

3. Run ```./release.sh```

4. Zip the whole folder using your favourite ZIP compression tool
