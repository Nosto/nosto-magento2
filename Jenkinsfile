#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('magento')
    FOO = 'moomoo'
    def BAR = 'hello'
  }

  agent any
  stages {
    stage('Prepare environment') {
      steps {
        echo "${MAGENTO}"
        echo "${env.MAGENTO}"
        echo "${env.MAGENTO_USR}"
        echo "${env.FOO}"
        echo "${params}"
        echo "${BAR}"
      }
    }
  }
}
