.. _adminscore:

***********
Admin Score
***********

The Admin Score tool is intended to give a very brief overview of how admin-worthy a user is.  This tool was originally developed by ScottyWong for use on the English Wikipedia.

Algorithm
=========

AdminScore takes the following factors into account

+----------------------------+------------+
| Activity                   | Multiplier |
+============================+============+
| Account Age (days)         | 1.25       |
+----------------------------+------------+
| Edit Count                 | 1.25       |
+----------------------------+------------+
| Has user page              | 1          |
+----------------------------+------------+
| Page Patrols               | 1          |
+----------------------------+------------+
| Blocks applied             |  1.4       |
+----------------------------+------------+
| Participation in AFDs      | 1.15       |
+----------------------------+------------+
| Recent activity (730 days) | 0.9        |
+----------------------------+------------+
| Participation at AIV       | 1.15       |
+----------------------------+------------+
| Use of edit summaries      | 0.8        |
+----------------------------+------------+
| Namespaces                 | 1          |
+----------------------------+------------+
| Pages Created (live)       | 1.4        |
+----------------------------+------------+
| Pages Created (deleted)    | 1.4        |
+----------------------------+------------+
| Participation at RFPP      | 1.15       |
+----------------------------+------------+

All factors are capped at 100, making a total possible admin score 1300.