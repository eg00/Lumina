import json
import sys
from app.main import get_image_model, get_text_model


def main() -> None:
    if len(sys.argv) < 3:
        print("Usage: python -m app.cli [embed-text|embed-images] [value]")
        sys.exit(1)

    cmd = sys.argv[1]
    val = sys.argv[2]

    if cmd == "embed-text":
        model = get_text_model()
        emb = model.encode(val, normalize_embeddings=True)
        print(json.dumps(emb.tolist()))
    elif cmd == "embed-images":
        from PIL import Image

        model = get_image_model()
        with Image.open(val) as img:
            rgb_img = img.convert("RGB")
            emb = model.encode(rgb_img, normalize_embeddings=True)
            print(json.dumps(emb.tolist()))


if __name__ == "__main__":
    main()
