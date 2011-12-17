Backend Views allows you to use Data Sources to create a custom listing of entries in the backend. This is especially useful for authors that need to filter or limit a set of entries by some rules and that are already familiar with the DS Editor.

## Installation

1. Upload the `backend_views` folder in this archive to your Symphony `extensions` folder.
2. Enable it by selecting the "Backend Views", choose _Enable_ from the _with-selected_ menu, then click _Apply_.
3. Go to System > Preferences to enable/disable "Backend Views".

## Usage

Once the extension is enabled, it parses all the existing Data Sources in order to find those which match one of the following types:

- Section
- Authors
- Static/Dynamic XML

If matches are found, DSes are listed in a special textarea in the Preferences page. For every selected Data Source, a "Backend View" page is created and can be accessed by the "Views" system menu. Inside a Backend View page is a table that accomodates a list of entries (based on the Data Source's return set) and whose columns you can control by playing with the "Included Elements" field of the DS Editor.

Supported features:

- Sorting and Limiting
- Filtering
