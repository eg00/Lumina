from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from PIL import Image
from sentence_transformers import SentenceTransformer
import os
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ai-worker")

app = FastAPI(title="Lumina AI Worker")
_text_model = None
_image_model = None


def get_text_model() -> SentenceTransformer:
    global _text_model
    if _text_model is None:
        model_name = os.getenv("CLIP_TEXT_MODEL", "sentence-transformers/clip-ViT-B-32-multilingual-v1")
        logger.info(f"Loading CLIP Text model: {model_name}...")
        _text_model = SentenceTransformer(model_name)
        logger.info("CLIP Text model loaded successfully.")
    return _text_model


def get_image_model() -> SentenceTransformer:
    global _image_model
    if _image_model is None:
        model_name = os.getenv("CLIP_IMAGE_MODEL", "clip-ViT-B-32")
        logger.info(f"Loading CLIP Image model: {model_name}...")
        _image_model = SentenceTransformer(model_name)
        logger.info("CLIP Image model loaded successfully.")
    return _image_model


class TextEmbedRequest(BaseModel):
    text: str


class ImageEmbedRequest(BaseModel):
    paths: list[str]


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/embed-text")
def embed_text(req: TextEmbedRequest):
    if not req.text.strip():
        raise HTTPException(status_code=400, detail="Text cannot be empty")

    model = get_text_model()
    embedding = model.encode(req.text, normalize_embeddings=True)
    return {"embedding": embedding.tolist()}


@app.post("/embed-images")
def embed_images(req: ImageEmbedRequest):
    model = get_image_model()
    results = {}

    for path in req.paths:
        try:
            if os.path.exists(path):
                with Image.open(path) as img:
                    rgb_img = img.convert("RGB")
                    emb = model.encode(rgb_img, normalize_embeddings=True)
                    results[path] = emb.tolist()
            else:
                logger.warning(f"File not found: {path}")
        except Exception as e:
            logger.error(f"Failed to process image {path}: {e}")

    return {"results": results}
