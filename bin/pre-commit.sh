#!/bin/sh

PROJECT=`php -r "echo dirname(dirname(realpath('$0')));"`
STAGED_FILES_CMD=`git diff --cached --name-only --diff-filter=ACMR HEAD | grep \\\\.*`

# Determine if a file list is passed
if [ "$#" -eq 1 ]
then
	oIFS=$IFS
	IFS='
	'
	SFILES="$1"
	IFS=$oIFS
fi
SFILES=${SFILES:-$STAGED_FILES_CMD}

for FILE in $SFILES
 do
 	FILES="$FILES $PROJECT/$FILE"
 done

if [ "$FILES" != "" ]
then
    echo "fix code style and update the commit"
	vendor/bin/php-cs-fixer fix
    git add $FILES
fi

if [ "$FILES" != "" ]
then
    echo "Static code analysis with PHPStan..."
    vendor/bin/phpstan analyse
	if [ $? != 0 ]
	then
		echo "Static code analysis failed. Fix the error before commit."
		exit 1
	fi
fi

if [ "$FILES" != "" ]
then
    echo "Linting Twig templates..."
    bin/console lint:twig templates
	if [ $? != 0 ]
	then
		echo "Twig linting failed. Fix the error before commit."
		exit 1
	fi
fi

if [ "$FILES" != "" ]
then
    echo "Linting Yaml files..."
    bin/console lint:yaml config
	if [ $? != 0 ]
	then
		echo "Yaml linting failed. Fix the error before commit."
		exit 1
	fi
fi

if [ "$FILES" != "" ]
then
    echo "Linting container..."
    bin/console lint:container
	if [ $? != 0 ]
	then
		echo "Yaml linting failed. Fix the error before commit."
		exit 1
	fi
fi

if [ "$FILES" != "" ]
then
    echo "Linting translation files..."
    bin/console lint:xliff translations
	if [ $? != 0 ]
	then
		echo "Yaml linting failed. Fix the error before commit."
		exit 1
	fi
fi

exit $?
