#!/usr/bin/env groovy

pipeline {

  withCredentials([usernamePassword(credentialsId: 'amazon', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
    agent {
      dockerfile {
        additionalBuildArgs '--build-arg REPOUSER=foo --build-arg REPOPASS=bar'
      }
    }
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
      }
    }
    stage('Prebuild') {
      steps {
        script {
          sh 'echo hi'
        }
      }
    }
  }
}
