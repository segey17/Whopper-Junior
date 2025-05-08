<template>
    <v-card>
      <v-card-title>{{ task.title }}</v-card-title>
      <v-card-text>{{ task.description }}</v-card-text>
      <v-card-actions>
        <v-btn color="primary" @click="editTask">Edit</v-btn>
        <v-btn color="error" @click="deleteTask">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </template>
  
  <script>
  export default {
    props: ['task'],
    methods: {
      editTask() {
        // Логика редактирования задачи
        console.log('Editing task...');
      },
      deleteTask() {
        // Логика удаления задачи
        console.log('Deleting task...');
      }
    }
  };
  </script>