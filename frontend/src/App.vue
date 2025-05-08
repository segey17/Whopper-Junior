<template>
    <v-app>
      <v-app-bar app color="primary" dark>
        <v-toolbar-title>Task Manager</v-toolbar-title>
        <v-spacer></v-spacer>
        <v-btn text to="/login">Login</v-btn>
        <v-btn text to="/register">Register</v-btn>
      </v-app-bar>
      <v-content>
        <router-view></router-view>
      </v-content>
    </v-app>
  </template>
  
  <script>
  export default {};
  </script>