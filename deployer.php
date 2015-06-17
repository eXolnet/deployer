<?php

require_once __DIR__.'/config.php';

if ($argc !== 2) {
	echo 'Usage: '.$argv[0].' tag/branch'.PHP_EOL;
	exit(-1);
}

$tag = $argv[1];

if ('`whoami`' !== $runAs) {
	echo 'This script must be run as user \''.$runAs.'\''.PHP_EOL;
	exit(-2);
}

function run($command)
{
	echo $command.PHP_EOL;
	passthru($command, $returnStatus);
	return $returnStatus;
}

$currentSymlink = $rootDirectory.'/current';
if (file_exists($currentSymlink)) {
	$returnStatus = run('cd '.$currentSymlink.' && git diff --quiet --ignore-submodules > /dev/null 2>&1');
	if ($returnStatus !== 0) {
		echo 'There are changed files. Please commit them before deploying a new release.'.PHP_EOL;
		exit(-3);
	}
}

// Create releases directory
$releasesDirectory = $rootDirectory.'/releases';
if ( ! file_exists($releasesDirectory)) {
	mkdir($releasesDirectory, 0777, true);
}

// Create shared directory
$sharedDirectory = $rootDirectory.'/shared';
if ( ! file_exists($releasesDirectory)) {
	mkdir($releasesDirectory, 0777, true);
}

// Create release directory in releases directory
$releaseIdentifier = date('YmdHis');
$currentReleaseDirectory = $releasesDirectory.'/'.$releaseIdentifier;
mkdir($currentReleaseDirectory, 0777, true);

// Symlink release directory to release directory in releases directory
$releaseSymlink = $rootDirectory.'/release';
if (file_exists($releaseSymlink)) {
	unlink($releaseSymlink);
}
symlink($currentReleaseDirectory, $releaseSymlink);

// Create git repos directory
// Git clone || Git fetch
$reposDirectory = $rootDirectory.'/repos';
if ( ! file_exists($reposDirectory)) {
//	mkdir($reposDirectory, 0777, true);
	run('git clone '.$gitRepository.' '.$reposDirectory);
} else {
	run('cd '.$reposDirectory. ' && git fetch');
}

// Checkout specified tag
run('cd '.$reposDirectory. ' && git checkout -f '.$tag);

// Copy/Rsync to release directory
run('rsync -avz '.$reposDirectory.'/ '.$releaseSymlink);

// Update/Init submodules
run('cd '.$releaseSymlink. ' && git submodule update --init');

// Remove .git directory
//run('rm -Rf '.$releaseSymlink.'/.git');

// Setup shared directories and shared files

// Chmod writable directories and files

// Run npm install
run('cd '.$releaseSymlink. ' && npm install');

// Run bower install
run('cd '.$releaseSymlink. ' && bower install');

// Run grunt build:release
run('cd '.$releaseSymlink. ' && grunt build:release');

// Run wget composer
run('cd '.$releaseSymlink. ' && wget -nc http://getcomposer.org/composer.phar');

// Run php composer.phar install
run('cd '.$releaseSymlink. ' && php composer.phar self-update && php composer.phar install');

// Run php artisan migrate
run('cd '.$releaseSymlink. ' && php artisan migrate');

// Run php artisan db:seed
//run('cd '.$releaseSymlink. ' && php db:seed');

// Update the current symlink
if (file_exists($currentSymlink)) {
	unlink($currentSymlink);
}
symlink($currentReleaseDirectory, $currentSymlink);

// Remove current release symlink
unlink($releaseSymlink);

// Cleanup old releases
$oldReleases = glob($releasesDirectory.'/*');
$keepCount = $keepReleases;
while ($keepCount--) {
	array_pop($oldReleases);
}

foreach ($oldReleases as $releaseToDelete) {
	run('rm -Rf '.$releaseToDelete);
}
