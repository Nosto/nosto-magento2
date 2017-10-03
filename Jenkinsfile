#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('magento')
  }

  agent any
  stages {
    stage('Prepare environment') {
      steps {
        echo "${MAGENTO}"
        echo "${env.MAGENTO}"
        echo "${env.MAGENTO_USR}"
      }
    }
  }
}
