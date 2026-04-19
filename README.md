# Star Citizen Questions

**Star Citizen Questions** is a WordPress plugin designed to collect questions in preparation for asking a developer during a **BarCitizen** event.

The plugin helps you gather, organize, and review questions from your community before the event starts. It also includes RSI Handle verification so you can keep submissions tied to real community members.

## What this plugin is for

BarCitizen events are often the best place to ask developers thoughtful, relevant questions in person. This plugin is intended to help your community:

- collect questions ahead of time
- avoid duplicate submissions
- group questions by event, location, or meetup
- review and export the final list before the event

This makes it easier to prepare a clean and relevant question list to bring to the developer discussion at the event.

## Features

- Front-end question submission form
- RSI Handle verification
- Group-based question separation
- Admin list view with custom columns
- Question export to TXT
- Danish and English-ready translation support

## How to use it

### 1. Install the plugin
Upload the plugin to your WordPress site and activate it like any other plugin.

### 2. Add the shortcode to a page
Place the shortcode on a page where you want visitors to submit questions:
```
[sc_questions group="your-event-name"]
```
You can also use:
```
[sc_questions group="barcitizen-herning"]
```

### 3. Tell users what the group is for
The `group` attribute is very important. It tells the plugin which event or collection the question belongs to.

For example, you might use different groups for:

- a specific BarCitizen event
- a city or region
- a meetup date
- a community chapter

## Why the `group` attribute matters

The `group` value is what keeps questions organized.

Without a group, all questions would be mixed together. With groups, you can:

- separate questions by event
- prevent duplicate submissions within the same event group
- export only the questions relevant to one specific event
- keep your question list focused and easier to manage

### Example
If you are running two events, you could use:
```
[sc_questions group="barcitizen-aarhus"]
```
and...
```
[sc_questions group="barcitizen-esbjerg"]
```

That way, each event gets its own question pool.

## Admin usage

In the WordPress admin area, the plugin adds a question post type where you can:

- view submitted questions
- see the RSI Handle associated with each question
- filter by group
- export questions to a text file

## Exporting questions

The plugin includes an export page in the admin area. Use it to generate a TXT file with the questions for a selected group.

This is useful when you want to bring the final list to a BarCitizen event and review it with the developer.

## Translation support

The plugin uses the `sc-questions` text domain and is prepared for translation.

English is the base language, and Danish translations can be placed in the `languages` directory.

## Recommended workflow for events

A good workflow is:

1. Create a group for the event
2. Put the shortcode on a page
3. Share the page with your community
4. Collect questions in advance
5. Review and clean up the list in the admin area
6. Export the final list before the event
7. Use the list during the BarCitizen developer Q&A

## Requirements

- WordPress
- PHP 7.4 or compatible
- Write access to the WordPress admin area for managing questions and exports

## License

GPL-3.0