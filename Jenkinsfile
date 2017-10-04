#!/usr/bin/env groovy

pipeline {

  agent { dockerfile true }
  environment {
    REPO = credentials('magento')
  }

  stages {
    stage('Prepare environment') {
      steps {
        checkout scm
      }
    }

    stage('Update Dependencies') {
      steps {
        sh "composer config repositories.0 composer https://repo.magento.com"
        sh "composer config http-basic.repo.magento.com $REPO_USR $REPO_PSW"
        sh "composer install"
      }
    }

    stage('Code Sniffer') {
      steps {
        catchError {
          sh "./vendor/bin/phpcbf --standard=ruleset.xml || true"
          sh "./vendor/bin/phpcs --standard=ruleset.xml --report=checkstyle --report-file=phpcs.xml || true"
        }
        checkstyle pattern: 'phpcs.xml', unstableTotalAll:'0'
      }
    }

    stage('Mess Detection') {
      steps {
        catchError {
          sh "./vendor/bin/phpmd . xml codesize,naming,unusedcode,controversial,design --exclude vendor,var,build,tests --reportfile phpmd.xml || true"
        }
        pmd pattern: 'phpmd.xml', unstableTotalAll:'0'
      }
    }

    stage('Phan Analysis') {
      steps {
        catchError {
          sh "./vendor/bin/phan --config-file=phan.php --output-mode=checkstyle --output=phan.xml || true"
        }
        checkstyle pattern: 'phan.xml', unstableTotalAll:'0'
      }
    }

    stage('Package') {
      steps {
        script {
          version = sh(returnStdout: true, script: 'git rev-parse --short HEAD').trim()
          sh "composer archive --format=zip --file=${version}"
          sh "composer validate-archive -- ${version}.zip"
        }
        archiveArtifacts "${version}.zip"
      }
    }

    stage('Test') {
      steps {
        script {
          sh 'echo $BRANCH_NAME'
          sh 'pwd'
          sh 'whoami'
          sh 'groups'
          sh 'groups plugins'
          sh 'groups www-data'
          sh 'ls -lah /var/www/html'
          sh 'ls -lah /var/www/html/community-edition/'
          sh 'cd /var/www/html/community-edition'
          sh 'composer remove nosto/module-nostotagging'
          sh "composer install nosto/module-nostotagging#dev-develop"
        }
      }
    }

  }

  post {
    always {
      deleteDir()
    }
  }
}
