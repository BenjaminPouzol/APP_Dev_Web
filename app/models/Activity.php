<?php

class Activity {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM activities ORDER BY start_time ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM activities WHERE idactivities = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities 
            (title, description, location, city, start_time, end_time, max_participants, visibility, status, creator_id, created_at)
            VALUES 
            (:title, :description, :location, :city, :start_time, :end_time, :max_participants, :visibility, 'active', :creator_id, NOW())
        ");
        return $stmt->execute([
            'title'            => $data['title'],
            'description'      => $data['description'],
            'location'         => $data['location'],
            'city'             => $data['city'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'max_participants' => $data['max_participants'],
            'visibility'       => $data['visibility'],
            'creator_id'       => $data['creator_id'],
        ]);
    }
}