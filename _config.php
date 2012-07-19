<?php

global $databaseConfig;
if (isset($databaseConfig['type'])) SearchUpdater::bind_manipulation_capture();
