#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('magento')
  }

  agent any
  stages {
    stage('Prepare environment') {
      steps {
        echo "${env.MAGENTO_USR}"
      }
    }
  }
}
