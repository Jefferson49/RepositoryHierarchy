##  Repository Hierarchy
A [weebtrees](https://webtrees.net) custom module to present the structure of a repository and its sources in a hierarchical manner. The module uses delimiter expressions to extract call number categories from the call numbers of the sources. Based on the extracted categories, a hierarchical tree of call number categories and the related sources is constructed and shown.

Example call numbers:
+ Fonds A / Record group X / Series 1 / Folder A23 / Source 11
+ Fonds A / Record group X / Series 1 / Folder A23 / Source 12
+ Fonds A / Record group X / Series 2 / Folder B82 / Source 51

Delimiter expression: " / "

Resulting repository hierarchy:
+ Fonds A
    + Record group X
        + Series 1
            + Folder A23
                + Source 11
                + Source 12
        + Series 2
            + Folder B82
                + Source 51
				
<a name="Contents"></a>				
##  Table of Contents
This README file contains the following main sections:
*   [What are the benefits of using this module?](#Benefits)
*   [Screenshot](#Screenshot)
*   [Installation](#Installation)
*   [Webtrees Version](#Version)
*   [Some background about archive and library management](#Background)
*   [Concepts of the Repository Hierarchy Module](#Concepts)
*   [How the module maps to Gedcom and to archive management concepts](#Mapping)
*   [**How to use the module?**](#Usage)
*   [Preferences](#Preferences)
*   [Github Repository](#Github)

<a name="Benefits"></a>
##  What are the benefits of using this module?
+ Improve the overview of sources in repositories
+ Improve the insight into repository structures and content
+ Find and remove inconsistencies between call numbers in repositories
+ Get additional features to rename call number categories (or groups of call numbers)
+ Get better support to design an archive arrangement/classification for your own archive and manage the corresponding call numbers
+ Use the generated hierarchical repository list as a finding aid (i.e. table of content or catalog) for repositories

<a name="Screenshot"></a>
##  Screenshot
![Screenshot](resources/img/screenshot.jpg)

<a name="Installation"></a>
##  Installation
+ Download the [latest release](https://github.com/Jefferson49/RepositoryHierarchy/releases/latest) of the module
+ Copy the folder "repository_hierarchy" into the "module_v4" folder of your webtrees installation
+ Check if the module is activated in the control panel:
    + Login to webtrees as an administrator
	+ Go to "Control Panel/All Modules", and find the module called "Repository Hierarchy" (or corresponding translation)
	+ Check if it has a tick for "Enabled"

<a name="Version"></a>
##  Webtrees version  
The latest release of the module was developed and tested with [webtrees 2.1.6](https://webtrees.net/download), but should also run with any other webtrees 2.1 version.

<a name="Background"></a>
##  Some background about archive and library management
In archive (or library) management, archival arrangements, library classifications, finding aids, and call numbers are frequently used to:
+ define a structure for an archive
+ assign item numbers to the sources in the archive
+ provide a catalog or finding aid for the archive

In the following, some of the typical concepts are briefly described.

###  Archival Arrangement
[Wikipedia](https://en.wikipedia.org/wiki/Finding_aid): "Arrangement is the manner in which \[the archive] has been ordered \[...]. Hierarchical levels of arrangement are typically composed of record groups containing series, which in turn contain boxes, folders, and items."

###  Library classification
[Wikipedia](https://en.wikipedia.org/wiki/Library_classification): "A library classification is a system of knowledge distribution by which library resources are arranged and ordered systematically."

###  Finding Aids
[Wikipedia](https://en.wikipedia.org/wiki/Finding_aid): "A finding aid for an archive is an organization tool, a document containing detailed, indexed, and processed metadata and other information about a specific collection of records within an archive."

###  Call numbers
[Wikipedia](https://en.wikipedia.org/wiki/Library_classification): "\[...] a call number (essentially a book's address) based on the classification system in use at the particular library will be assigned to the work using the notation of the system."

###  Relationship between Archival Arrangement and Call numbers
A lot of archives (and libraries) map the archival arrangement (or library classification) into the call numbers of the sources. 

For example, the archive might have the following arrangement:
+ Fonds
    + Record group
        + Series
            + Folder
                + Source

In this case, the call numbers might have the following structure:

**"Fonds/Record group/Series/Folder/Source"**

Therefore, the hierarchy of the archival arrangement is represented in the "route" or the "path" of the call number. 

<a name="Concepts"></a>
##  Concepts of the Repository Hierarchy Module
In the following, the concepts of the RepositoryHierarchy module are described. 

###  Call number categories
In the module, a new concept "Call numbers category" is introduced. Call number categories are defined as hierarchical elements, which constitute the structure of an archival arrangement.

###  Relationship between call number categories, call numbers, and delimiters
Call number categories are extracted form call numbers. The module identifies sub-strings in call numbers as call number categories by using delimiters. A chosen delimiter (or a set of delimiters) cuts the full call number into sub-strings of call number categories.

Example call number structure:
"Fonds/Record group/Series/Folder/Source"

In this case, the module identifies the following strings as **call number categories**:
+ Fonds
+ Record group
+ Series
+ Folder
+ Source

Based on the identified call number categories, the module creates the following hierarchical structure for the archive:
+ Fonds
    + Record group
        + Series
            + Folder
                + Source
				
###  Delimiter expressions for call numbers
A delimiter is a sequence of one or more characters for specifying the boundary between separate, independent regions in a text. In the RepositoryHierarchy module, delimiters are used to cut call numbers into sub-strings of call number categories. The call number categories will be used to construct a hierarchy of call numbers.

<a name="Mapping"></a>
##  How the module maps to Gedcom and to archive management concepts
In order to manage archives and sources, Gedcom and webtrees basically provide the following data structures:
+ Repository
+ Source
+ Call number (of a source within a repository)

The following table describes how the concepts from archive and library management are mapped to Gedcom/webtrees and the Repository Hierarchy custom module:

|Archive/Library Concept|Gedcom/webtrees data structures|Repository Hierarchy Module|
|:------|:--------------|:---------------------------|
|Archive,<br>Library|Repository|Repository|
|Archival Arrangement,<br>Library Classification|-|Hierarchy of call number categories|
|Fonds,<br>Record group,<br>Series,<br>Folder|-|Call number category|
|Item,<br>file,<br>book|Source|Source|
|Call number|Call number|Call number|
|Finding aid|List of sources for a selected repository|List of sources in a hierarchy of call number categories for a selected repository|

<a name="Usage"></a>
##  How to use the module?

###  Usage of a single delimiter
A single delimiter is used by providing a single character or a sequence of characters in the related input form ("delimiter expression"). 

Example:
+ Call numbers:
    + Fonds/Series/Item 1
    + Fonds/Series/Item 2
+ Delimiter expression: **/**
+ Repository Hierarchy:
    + Fonds/
        + Series/
            + Item 1
            + Item 2

###  Usage of a set of delimiters
A set of delimiters is used by providing the delimiters in the input form ("delimiter expression") separated by "**;**".

Example:
+ Call numbers:
    + Fonds/Series-Item 1
    + Fonds/Series-Item 2
+ Delimiter expression: **/;-**
+ Repository Hierarchy:
    + Fonds/
        + Series-
            + Item 1
            + Item 2

In a set of delimiters, the delimiters are evaluated from left to right, i.e. the most left delimiter is evaluated first. Delimiters will also be applied recursively for as many matches as possible. Only if no further matches of a delimiter are found, the next delimter is evaluated.

Example:
+ Call number:
    + Fonds A/Record-group/Series A-Nr. 7
+ Delimiter expression: **/;-**
+ Repository Hierarchy:
    + Fonds A
        + Record-group
            + Series A
                + Nr. 7		

I.e. the "-" delimiter in "Record-group" is not evaluated, because the "/" delimiter is evaluated first. After all matches of the "/" delimiter have been evaluated, the "-" delimiter is found in "Series A-Nr. 7".
	
###  Usage of a regular expression for the delimiter
A regular expression is used by providing it in the input form ("delimiter expression"). The regular expression needs to contain the delimiter in brackets. This provides a much more powerful way to specify delimiters.

Please note, that the "full" regular expression will be used to find a certain pattern in the call numbers. However, **only the characters in the brackets** ("the match" of the regular expression) **will be used as the delimiter**.

Example:
+ Call numbers:
    + Film Number 5
    + Film Number 8
+ Delimiter expression: **Film( )Number**
+ Repository Hierarchy:
    + Film
        + Number 5
        + Number 8

In this example, the delimiter is the space character in the brackets, i.e. "**( )**". However, the full pattern "**Film( )Number**" is used to find corresponding strings. Therefore, only space characters, which match the pattern, are identified as delimiter. Other space characters, are NOT identified as delimiter.

###  Usage of a set of regular expressions for the delimiter
A set of regular expressions can be used by providing several regular expressions in the input form ("delimiter expression") separated by "**;**". It is also possible to mix simple delimiters (i.e. a single character or sequence of characters) with regular expressions.

Example:
+ Call numbers:
    + Fonds A, Biography Number 1
    + Fonds D, Photo Number 7
+ Delimiter expression: **Fonds \[A-D](, );( )Number**
+ Repository Hierarchy:
    + Fonds A,
        + Biography
            + Number 1
    + Fonds D,
        + Photo
            + Number 7

Like for a set of simple delimiters, the delimiter expressions are evaluated from left to right. Please refer to the description and example for a set of simple delimiters.

###  Save and load options
By pressing certain radio buttons in the front end, certain load and save operations can be executed while pressing the "view" button. 

####  Save and load a repository
If the "save repository" radio button is activated while the "view" button is pressed, the currently selected repository will be stored for the active user. 

If the "load repository" radio button is activated while the "view" button is pressed, the module will load a stored repository of the user if already stored.

####  Save and load a delimiter expression
If the "save delimiter expression" radio button is activated while the "view" button is pressed, the current delimiter expression will be stored for the active user. If the user is administrator, the expression will also (parallely) be stored as adminstrators' delimiter expression.

If the "load delimiter expression" radio button is activated while the "view" button is pressed, the module will load a stored delimiter expression of the user if already stored.

If the "load delimiter expression from administrator" radio button is activated while the "view" button is pressed, the module will load a stored delimiter expression of the administrator(s) if available.

###  Rename a call number category
By opening the "Rename" link close to a call number category, a data fix page with a search/replace form is opened, where the name of the chosen call number can be modified.

###  Add a new source to a call number category
By opening the "Add new source" link close to a call number category, a form is opened, which allows to add a new source to the chosen call number. When opening the form, a "{new}" placeholder is inserted, which should be modified by the user.

While the "{new}" placeholder should be modified, the rest of the call number, which consists of the call number category hierarchy should only be modified if the "route" or "path" of the call number category shall also be changed. If the intention is to simple add a new source to an existing call number category, only the "{new}" placeholder should be changed.

<a name="Prefences"></a>
##  Preferences
The following preferences can be activated/deactivated by administrators in the control panel:
+ Show label before call number category.
+ Show help icon after label for delimiter expression.
+ Show help link after label for delimiter expression.
+ Use truncated categories. The call number categories will be truncated and shown without the trunk.
+ Use truncated call numbers. The call numbers will be truncated and shown without call number category.
+ Show the title of the sources.
+ Show the XREF of the sources.
+ Show the author of the sources.
+ Show the date range of the sources.
+ Allow users to load stored delimiter expressions from administrator.

Select/Unselect preferences by hitting the related radio buttons.

<a name="Github"></a>
##  Github repository  
https://github.com/Jefferson49/RepositoryHierarchy
