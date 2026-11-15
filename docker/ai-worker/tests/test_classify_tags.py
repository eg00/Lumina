import pytest
from fastapi.testclient import TestClient
from PIL import Image
import tempfile
import os
from app.main import app

client = TestClient(app)

def test_classify_tags_endpoint():
    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as f:
        img = Image.new("RGB", (100, 100), color="blue")
        img.save(f.name)
        img_path = f.name

    try:
        response = client.post("/classify-tags", json={
            "paths": [img_path],
            "candidate_tags": ["blue ocean", "red car"],
            "threshold": 0.1,
            "top_k": 2
        })
        assert response.status_code == 200
        data = response.json()
        assert "results" in data
        assert img_path in data["results"]
        tags = data["results"][img_path]
        assert len(tags) > 0
        assert "tag" in tags[0]
        assert "score" in tags[0]
    finally:
        if os.path.exists(img_path):
            os.remove(img_path)
