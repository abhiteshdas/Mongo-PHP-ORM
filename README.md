Mongo-PHP-ORM
=============

    This is incarnation 2 of Homebase framework mongo ORM.
    Product is in beta/stabilisation stage. Not used in production yet.
    It still depends on some Homebase framework classes and functions
    Soon we'll provide standalone tested library

# FEATURES (most important)
* **ORM** with calc fields, relationships
* **Type support** (complex/custom type support)


# FEATURES
* simple **compact** clutter-less **syntax**
* low level and **ORM** level extensions
  * maps mongo records to objects
  * you can extend this objects : add business logic, getters, setters, calculated fields
* **relation** support (has_one, has_many)
* field **aliases** ( long keys be unnessecary burden for bson based storage)
* **type support** 
  * basic and complex types (name, ip, phone, email, url, ...)
  * you can extend/change/add your types
  * types are used for queries and updates / inserts
  * types are supported on ORM and non ORM levels
* lots of useful functions and shortcuts
  * group by, mix/max/avg/sum/...
  * index enforcement, mysql migration
* declarative and easy to support config
  * configure autoload fields, in-memory entity caching
* almost no overhead, written with performance in mind

# DETAILS
* All our primary keys are ints. 
  * We do not like mongo primary keys, we use our sequence generator. Speed is good enough. For batch jobs we reserve blocks of IDs.
* Several Layers of Abstraction:
  * Mongo Wrapper (M_Collection)
    * adds new shortcut functions, simlification of existing mongo functions
  * M_TypedCollection
    * type support for mongo, supports basic and complex/custom classes
  * M_Object (ORM level)
    * default implementation / base class for your objects
    * you may extend it or use default implementation
  * M_Type
    * type support. int, string, float, date, datetime, ip, email, url, array, arrays_of
    * allows you to define applyType method (used before save and before lookups)
    * provides magic fields:
       * alternative field getter / setter
       * ex. dates are stored as ints, by accessing magic field "_datefield" get human format "May 25, 2012"

AUTHOR
------
  Sergey Porfiriev <parf@comfi.com>

COPYRIGHT
---------
  (C) 2010-2012 Comfi.com

LICENSE
-------
  The MIT License (MIT) - http://www.opensource.org/licenses/mit-license.php
